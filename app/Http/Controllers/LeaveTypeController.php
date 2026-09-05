<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeaveAccrual;
use App\Facades\Tenancy;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LeaveTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        return response()->json([
            'data' => LeaveType::query()->orderBy('name')->get()->map(fn (LeaveType $t): array => $this->transform($t)),
        ]);
    }

    public function show(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('view', $leaveType);

        return response()->json(['data' => $this->transform($leaveType)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveType::class);

        $leaveType = LeaveType::query()->create($this->validated($request));

        return response()->json(['data' => $this->transform($leaveType->fresh())], 201);
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->authorize('update', $leaveType);

        $leaveType->update($this->validated($request, $leaveType));

        return response()->json(['data' => $this->transform($leaveType->fresh())]);
    }

    public function destroy(LeaveType $leaveType): Response
    {
        $this->authorize('delete', $leaveType);

        abort_if($leaveType->requests()->exists(), 409, 'This leave type has requests and cannot be deleted.');
        $leaveType->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LeaveType $leaveType = null): array
    {
        $required = $leaveType === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required, 'string', 'max:40', 'alpha_dash',
                Rule::unique('leave_types', 'code')->where('tenant_id', Tenancy::id())->ignore($leaveType?->id)->whereNull('deleted_at'),
            ],
            'color' => ['nullable', 'string', 'max:9'],
            'default_days_per_year' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'accrual' => ['nullable', new Enum(LeaveAccrual::class)],
            'is_paid' => ['boolean'],
            'requires_approval' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'min_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'allow_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'gender_restriction' => ['nullable', 'in:male,female'],
            'employment_type_id' => ['nullable', Rule::exists('employment_types', 'id')->where('tenant_id', Tenancy::id())],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(LeaveType $type): array
    {
        return [
            'uuid' => $type->uuid,
            'name' => $type->name,
            'code' => $type->code,
            'color' => $type->color,
            'default_days_per_year' => $type->default_days_per_year,
            'accrual' => $type->accrual->value,
            'is_paid' => $type->is_paid,
            'requires_approval' => $type->requires_approval,
            'requires_attachment' => $type->requires_attachment,
            'min_notice_days' => $type->min_notice_days,
            'max_consecutive_days' => $type->max_consecutive_days,
            'allow_carry_forward' => $type->allow_carry_forward,
            'gender_restriction' => $type->gender_restriction,
            'is_active' => $type->is_active,
        ];
    }
}
