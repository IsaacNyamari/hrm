<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Enums\LeaveAccrual;
use App\Exceptions\LeaveException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Panel extends Component
{
    use BindsTenant;

    public ?string $leaveTypeUuid = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public bool $halfDayStart = false;

    public bool $halfDayEnd = false;

    public string $reason = '';

    public bool $showTypeForm = false;

    public ?string $editingTypeUuid = null;

    /** @var array<string, mixed> */
    public array $typeForm = [];

    public function submit(LeaveService $service): void
    {
        $this->validate([
            'leaveTypeUuid' => ['required'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            Flux::toast(variant: 'danger', text: __('Your account is not linked to an employee record.'));

            return;
        }

        $type = LeaveType::query()->where('uuid', $this->leaveTypeUuid)->firstOrFail();

        try {
            $service->request(
                $employee,
                $type,
                CarbonImmutable::parse($this->startDate),
                CarbonImmutable::parse($this->endDate),
                [
                    'half_day_start' => $this->halfDayStart,
                    'half_day_end' => $this->halfDayEnd,
                    'reason' => $this->reason ?: null,
                ],
                auth()->user(),
            );

            $this->reset(['startDate', 'endDate', 'reason', 'halfDayStart', 'halfDayEnd']);
            Flux::toast(variant: 'success', text: __('Leave request submitted.'));
        } catch (LeaveException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function approve(string $uuid, LeaveService $service): void
    {
        $this->decide($uuid, true, $service);
    }

    public function reject(string $uuid, LeaveService $service): void
    {
        $this->decide($uuid, false, $service);
    }

    public function cancelRequest(string $uuid, LeaveService $service): void
    {
        $request = LeaveRequest::query()->where('uuid', $uuid)->first();

        if ($request === null || Gate::denies('cancel', $request)) {
            Flux::toast(variant: 'danger', text: __('You cannot cancel that request.'));

            return;
        }

        try {
            $service->cancel($request, auth()->user());
            Flux::toast(variant: 'success', text: __('Leave request cancelled.'));
        } catch (LeaveException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    private function decide(string $uuid, bool $approved, LeaveService $service): void
    {
        $request = LeaveRequest::query()->where('uuid', $uuid)->first();

        if ($request === null || Gate::denies('decide', $request)) {
            Flux::toast(variant: 'danger', text: __('You cannot act on that request.'));

            return;
        }

        try {
            $service->decide($request, auth()->user(), $approved);
            Flux::toast(variant: 'success', text: $approved ? __('Leave request approved.') : __('Leave request rejected.'));
        } catch (LeaveException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startCreateType(): void
    {
        Gate::authorize('create', LeaveType::class);
        $this->resetErrorBag();
        $this->editingTypeUuid = null;
        $this->typeForm = $this->blankTypeForm();
        $this->showTypeForm = true;
    }

    public function startEditType(string $uuid): void
    {
        $type = LeaveType::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $type);

        $this->resetErrorBag();
        $this->editingTypeUuid = $type->uuid;
        $this->typeForm = [
            'name' => $type->name,
            'code' => $type->code,
            'default_days_per_year' => (string) $type->default_days_per_year,
            'accrual' => $type->accrual->value,
            'is_paid' => $type->is_paid,
            'requires_approval' => $type->requires_approval,
            'is_active' => $type->is_active,
        ];
        $this->showTypeForm = true;
    }

    public function saveType(): void
    {
        $data = $this->validate([
            'typeForm.name' => ['required', 'string', 'max:120'],
            'typeForm.code' => ['required', 'string', 'max:40', 'alpha_dash'],
            'typeForm.default_days_per_year' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'typeForm.accrual' => ['required', 'string'],
            'typeForm.is_paid' => ['boolean'],
            'typeForm.requires_approval' => ['boolean'],
            'typeForm.is_active' => ['boolean'],
        ])['typeForm'];

        $attributes = [
            'name' => $data['name'],
            'code' => $data['code'],
            'default_days_per_year' => $data['default_days_per_year'] !== '' ? (float) $data['default_days_per_year'] : 0,
            'accrual' => LeaveAccrual::from($data['accrual']),
            'is_paid' => (bool) ($data['is_paid'] ?? false),
            'requires_approval' => (bool) ($data['requires_approval'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($this->editingTypeUuid === null) {
            Gate::authorize('create', LeaveType::class);
            LeaveType::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Leave type added.'));
        } else {
            $type = LeaveType::query()->where('uuid', $this->editingTypeUuid)->firstOrFail();
            Gate::authorize('update', $type);
            $type->update($attributes);
            Flux::toast(variant: 'success', text: __('Leave type updated.'));
        }

        $this->typeForm = $this->blankTypeForm();
        $this->editingTypeUuid = null;
        $this->showTypeForm = false;
    }

    public function removeType(string $uuid): void
    {
        $type = LeaveType::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $type);

        if ($type->requests()->exists()) {
            Flux::toast(variant: 'danger', text: __('This leave type has requests and cannot be deleted.'));

            return;
        }

        $type->delete();
        Flux::modal('delete-leave-type-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Leave type deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankTypeForm(): array
    {
        return [
            'name' => '', 'code' => '', 'default_days_per_year' => '21', 'accrual' => 'none',
            'is_paid' => true, 'requires_approval' => true, 'is_active' => true,
        ];
    }

    public function render(LeaveService $service): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $year = CarbonImmutable::now()->year;

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();
        $canManageTypes = $user?->can('leave.manage');
        $canOversee = $user?->can('leave.approve') || $canManageTypes;

        $balances = [];
        $requests = [];

        if ($employee !== null) {
            foreach ($types as $type) {
                $balance = $service->balanceFor($employee, $type, $year);
                $balances[] = [
                    'name' => $type->name,
                    'remaining' => $balance->remainingDays(),
                    'used' => $balance->used_days,
                    'pending' => $balance->pending_days,
                ];
            }

            $requests = $employee->leaveRequests()->with('leaveType:id,name')->latest('start_date')->limit(20)->get();
        }

        $toReview = $canOversee
            ? LeaveRequest::query()->where('status', 'pending')->with(['employee:id,first_name,last_name', 'leaveType:id,name'])->latest('id')->get()
            : collect();

        return view('livewire.leave.panel', [
            'hasEmployee' => $employee !== null,
            'leaveTypes' => $types,
            'balances' => $balances,
            'requests' => $requests,
            'toReview' => $toReview,
            'canOversee' => $canOversee,
            'canManageTypes' => $canManageTypes,
            'allTypes' => $canManageTypes ? LeaveType::query()->orderBy('name')->get() : collect(),
            'accruals' => LeaveAccrual::cases(),
        ]);
    }
}
