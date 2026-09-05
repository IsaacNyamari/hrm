<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Expense;
use App\Services\Expenses\ExpenseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $user = $request->user();
        $canViewAll = $user?->can('expenses.view') || $user?->can('expenses.manage') || $user?->can('expenses.approve');
        $ownEmployeeId = $user?->employee?->getKey();
        $managedIds = $ownEmployeeId === null ? [] : Employee::query()->where('manager_id', $ownEmployeeId)->pluck('id')->all();

        $expenses = Expense::query()
            ->with('employee:id,first_name,last_name,employee_number')
            ->when(! $canViewAll, function ($q) use ($ownEmployeeId, $managedIds): void {
                $q->where(fn ($inner) => $inner->where('employee_id', $ownEmployeeId)->orWhereIn('employee_id', $managedIds));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_id') && $canViewAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->latest('id')
            ->get();

        return response()->json(['data' => $expenses->map(fn (Expense $e): array => $this->transform($e))]);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        $expense->load(['employee', 'items', 'approvals.approver:id,name']);

        return response()->json(['data' => $this->transform($expense, detailed: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incurred_on' => ['nullable', 'date'],
        ]);

        $employee = $this->resolveEmployee($request, isset($data['employee_id']) ? (int) $data['employee_id'] : null);

        $expense = Expense::query()->create([
            'reference' => $this->service->nextReference(),
            'employee_id' => $employee->getKey(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'currency' => $data['currency'] ?? Tenancy::getOrFail()->currency,
            'incurred_on' => $data['incurred_on'] ?? CarbonImmutable::now()->toDateString(),
            'submitted_by' => $request->user()?->getKey(),
        ]);

        return response()->json(['data' => $this->transform($expense->fresh(), detailed: true)], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'incurred_on' => ['nullable', 'date'],
        ]);

        $expense->fill($data)->save();

        return response()->json(['data' => $this->transform($expense->fresh(), detailed: true)]);
    }

    public function destroy(Expense $expense): Response
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->noContent();
    }

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('submit', $expense);

        $updated = $this->service->submit($expense, $request->user());

        return response()->json(['data' => $this->transform($updated->load(['items', 'approvals']), detailed: true)]);
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        return $this->decide($request, $expense, true);
    }

    public function reject(Request $request, Expense $expense): JsonResponse
    {
        return $this->decide($request, $expense, false);
    }

    public function reimburse(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('reimburse', $expense);

        $reference = $request->validate(['payment_reference' => ['nullable', 'string', 'max:120']])['payment_reference'] ?? null;

        $updated = $this->service->markReimbursed($expense, $reference, $request->user());

        return response()->json(['data' => $this->transform($updated)]);
    }

    private function decide(Request $request, Expense $expense, bool $approved): JsonResponse
    {
        $this->authorize('decide', $expense);

        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:1000']])['comment'] ?? null;
        $actor = $request->user();
        abort_if($actor === null, 401);

        $updated = $this->service->decide($expense, $actor, $approved, $comment);

        return response()->json(['data' => $this->transform($updated->load(['items', 'approvals.approver:id,name']), detailed: true)]);
    }

    private function resolveEmployee(Request $request, ?int $employeeId): Employee
    {
        if ($employeeId !== null) {
            abort_unless($request->user()?->can('expenses.manage'), 403);

            return Employee::query()->findOrFail($employeeId);
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Expense $expense, bool $detailed = false): array
    {
        $base = [
            'uuid' => $expense->uuid,
            'reference' => $expense->reference,
            'title' => $expense->title,
            'status' => $expense->status->value,
            'total_cents' => $expense->total_cents,
            'currency' => $expense->currency,
            'employee' => $expense->relationLoaded('employee') ? $expense->employee->fullName() : null,
            'incurred_on' => $expense->incurred_on?->toDateString(),
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'description' => $expense->description,
            'current_level' => $expense->current_level,
            'submitted_at' => $expense->submitted_at?->toIso8601String(),
            'decided_at' => $expense->decided_at?->toIso8601String(),
            'decision_note' => $expense->decision_note,
            'reimbursed_at' => $expense->reimbursed_at?->toIso8601String(),
            'payment_reference' => $expense->payment_reference,
            'items' => $expense->relationLoaded('items')
                ? $expense->items->map(fn ($i): array => [
                    'uuid' => $i->uuid,
                    'description' => $i->description,
                    'category' => $i->category->value,
                    'spent_on' => $i->spent_on->toDateString(),
                    'amount_cents' => $i->amount_cents,
                    'quantity' => $i->quantity,
                    'unit_rate_cents' => $i->unit_rate_cents,
                    'has_receipt' => $i->receipt_path !== null,
                ])->all()
                : [],
            'approvals' => $expense->relationLoaded('approvals')
                ? $expense->approvals->map(fn ($a): array => [
                    'level' => $a->level,
                    'approver_type' => $a->approver_type,
                    'status' => $a->status->value,
                    'comment' => $a->comment,
                    'by' => $a->relationLoaded('approver') ? $a->approver?->name : null,
                    'decided_at' => $a->decided_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }
}
