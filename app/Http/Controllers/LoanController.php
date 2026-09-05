<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Facades\Tenancy;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Loan::class);

        $user = $request->user();
        $canViewAll = $user?->can('payroll.view') || $user?->can('payroll.manage');
        $ownEmployeeId = $user?->employee?->getKey();

        $loans = Loan::query()
            ->with('employee:id,first_name,last_name,employee_number')
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $ownEmployeeId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->get();

        return response()->json(['data' => $loans->map(fn (Loan $l): array => $this->transform($l))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Loan::class);

        $data = $request->validate([
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'type' => ['required', new Enum(LoanType::class)],
            'reference' => ['nullable', 'string', 'max:60'],
            'principal_cents' => ['required', 'integer', 'min:1'],
            'interest_rate' => ['nullable', 'numeric', 'between:0,1'],
            'installments' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
        ]);

        $employeeId = $this->resolveEmployeeId($request, isset($data['employee_id']) ? (int) $data['employee_id'] : null);

        $interest = (int) round($data['principal_cents'] * ((float) ($data['interest_rate'] ?? 0)));
        $repayable = $data['principal_cents'] + $interest;
        $installmentCents = (int) ceil($repayable / $data['installments']);

        $loan = Loan::query()->create([
            'employee_id' => $employeeId,
            'type' => $data['type'],
            'reference' => $data['reference'] ?? null,
            'principal_cents' => $data['principal_cents'],
            'interest_rate' => $data['interest_rate'] ?? 0,
            'installments' => $data['installments'],
            'installment_cents' => $installmentCents,
            'outstanding_cents' => $repayable,
            'start_date' => $data['start_date'],
            'status' => LoanStatus::Pending,
        ]);

        return response()->json(['data' => $this->transform($loan)], 201);
    }

    public function approve(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('approve', $loan);

        abort_if($loan->status !== LoanStatus::Pending, 409, 'This loan is not pending approval.');

        $loan->forceFill([
            'status' => LoanStatus::Active,
            'approved_by' => $request->user()?->getKey(),
            'approved_at' => CarbonImmutable::now(),
        ])->save();

        return response()->json(['data' => $this->transform($loan)]);
    }

    public function cancel(Loan $loan): JsonResponse
    {
        $this->authorize('cancel', $loan);

        abort_if($loan->status !== LoanStatus::Pending, 409, 'Only a pending loan can be cancelled.');

        $loan->forceFill(['status' => LoanStatus::Cancelled])->save();

        return response()->json(['data' => $this->transform($loan)]);
    }

    public function schedule(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        $rows = [];
        $balance = $loan->principal_cents
            + (int) round($loan->principal_cents * $loan->interest_rate);
        $date = $loan->start_date;

        for ($i = 1; $i <= $loan->installments && $balance > 0; $i++) {
            $due = min($loan->installment_cents, $balance);
            $balance -= $due;
            $rows[] = [
                'installment' => $i,
                'due_on' => $date->toDateString(),
                'amount_cents' => $due,
                'balance_cents' => $balance,
            ];
            $date = $date->addMonthNoOverflow();
        }

        return response()->json(['data' => [
            'loan' => $this->transform($loan),
            'schedule' => $rows,
        ]]);
    }

    public function destroy(Loan $loan): Response
    {
        $this->authorize('delete', $loan);

        abort_if($loan->repayments()->exists(), 409, 'This loan has repayments and cannot be deleted.');
        $loan->delete();

        return response()->noContent();
    }

    private function resolveEmployeeId(Request $request, ?int $employeeId): int
    {
        if ($employeeId !== null) {
            abort_unless($request->user()?->can('payroll.manage'), 403);

            return $employeeId;
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Loan $loan): array
    {
        return [
            'uuid' => $loan->uuid,
            'employee' => $loan->employee?->fullName(),
            'type' => $loan->type->value,
            'principal_cents' => $loan->principal_cents,
            'installments' => $loan->installments,
            'installment_cents' => $loan->installment_cents,
            'outstanding_cents' => $loan->outstanding_cents,
            'start_date' => $loan->start_date->toDateString(),
            'status' => $loan->status->value,
        ];
    }
}
