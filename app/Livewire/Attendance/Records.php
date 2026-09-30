<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCorrectionService;
use App\Services\Attendance\AttendanceService;
use App\Support\Auditor;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Records extends Component
{
    use BindsTenant;
    use WithPagination;

    public bool $showForm = false;

    public ?string $editingUuid = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?string $deletingUuid = null;

    public string $deleteReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Attendance::class);
        $this->form = $this->blankForm();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Attendance::class);
        $this->resetErrorBag();
        $this->editingUuid = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function startEdit(string $uuid): void
    {
        $attendance = Attendance::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $attendance);

        $this->resetErrorBag();
        $this->editingUuid = $attendance->uuid;
        $this->form = [
            'employee_id' => (string) $attendance->employee_id,
            'work_date' => $attendance->work_date->toDateString(),
            'clock_in_at' => $attendance->clock_in_at?->format('Y-m-d\TH:i') ?? '',
            'clock_out_at' => $attendance->clock_out_at?->format('Y-m-d\TH:i') ?? '',
            'break_minutes' => (string) $attendance->break_minutes,
            'status' => $attendance->status->value,
            'notes' => (string) $attendance->notes,
            'reason' => '',
        ];
        $this->showForm = true;
    }

    /**
     * Manual add, edit and delete change records payroll and leave rely on, so
     * each needs a reason and leaves a before/after entry in the audit trail.
     */
    public function save(AttendanceService $service, Auditor $auditor, AttendanceCorrectionService $corrections): void
    {
        $data = $this->validate([
            'form.employee_id' => ['required', 'integer'],
            'form.work_date' => ['required', 'date'],
            'form.clock_in_at' => ['nullable', 'date'],
            'form.clock_out_at' => ['nullable', 'date', 'after_or_equal:form.clock_in_at'],
            'form.break_minutes' => ['nullable', 'integer', 'min:0'],
            'form.status' => ['required', 'string'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'form.reason' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: ['form.reason' => 'reason'])['form'];

        $attributes = array_filter([
            'clock_in_at' => $data['clock_in_at'] !== '' ? $data['clock_in_at'] : null,
            'clock_out_at' => $data['clock_out_at'] !== '' ? $data['clock_out_at'] : null,
            'break_minutes' => $data['break_minutes'] !== '' ? (int) $data['break_minutes'] : null,
            'status' => AttendanceStatus::from($data['status']),
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ], static fn ($v): bool => $v !== null);

        if ($corrections->requiresApproval()) {
            $this->requestApproval($corrections, $data, $attributes);

            return;
        }

        if ($this->editingUuid === null) {
            Gate::authorize('create', Attendance::class);
            $employee = Employee::query()->findOrFail((int) $data['employee_id']);
            $record = $service->recordManual($employee, CarbonImmutable::parse($data['work_date']), $attributes, (int) auth()->id());
            $auditor->log('attendance.manual_added', $record, ['reason' => $data['reason'], 'after' => $this->snapshot($record)]);
            Flux::toast(variant: 'success', text: __('Attendance record added.'));
        } else {
            $attendance = Attendance::query()->where('uuid', $this->editingUuid)->firstOrFail();
            Gate::authorize('update', $attendance);
            $before = $this->snapshot($attendance);
            $record = $service->recordManual($attendance->employee, $attendance->work_date, $attributes, (int) auth()->id());
            $auditor->log('attendance.manual_edited', $record, ['reason' => $data['reason'], 'before' => $before, 'after' => $this->snapshot($record->refresh())]);
            Flux::toast(variant: 'success', text: __('Attendance record updated.'));
        }

        $this->form = $this->blankForm();
        $this->editingUuid = null;
        $this->showForm = false;
    }

    public function askRemove(string $uuid): void
    {
        $attendance = Attendance::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $attendance);

        $this->resetErrorBag();
        $this->deletingUuid = $attendance->uuid;
        $this->deleteReason = '';
        Flux::modal('delete-attendance')->show();
    }

    public function remove(Auditor $auditor, AttendanceCorrectionService $corrections): void
    {
        $this->validate(['deleteReason' => ['required', 'string', 'min:5', 'max:500']], attributes: ['deleteReason' => 'reason']);

        $attendance = Attendance::query()->where('uuid', (string) $this->deletingUuid)->firstOrFail();
        Gate::authorize('delete', $attendance);

        if ($corrections->requiresApproval()) {
            $corrections->request('delete', $attendance->employee, $attendance->work_date, $attendance, null, $this->deleteReason, auth()->user());
            $this->deletingUuid = null;
            $this->deleteReason = '';
            Flux::modal('delete-attendance')->close();
            Flux::toast(variant: 'success', text: __('Deletion sent for approval.'));

            return;
        }

        $auditor->log('attendance.manual_deleted', $attendance, ['reason' => $this->deleteReason, 'before' => $this->snapshot($attendance)]);
        $attendance->delete();

        $this->deletingUuid = null;
        $this->deleteReason = '';
        Flux::modal('delete-attendance')->close();
        Flux::toast(variant: 'success', text: __('Attendance record deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Attendance $attendance): array
    {
        return AttendanceCorrectionService::snapshot($attendance);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     */
    private function requestApproval(AttendanceCorrectionService $corrections, array $data, array $attributes): void
    {
        $attributes['status'] = $attributes['status'] instanceof AttendanceStatus ? $attributes['status']->value : $attributes['status'];

        if ($this->editingUuid === null) {
            Gate::authorize('create', Attendance::class);
            $employee = Employee::query()->findOrFail((int) $data['employee_id']);
            $corrections->request('add', $employee, CarbonImmutable::parse($data['work_date']), null, $attributes, $data['reason'], auth()->user());
        } else {
            $attendance = Attendance::query()->where('uuid', $this->editingUuid)->firstOrFail();
            Gate::authorize('update', $attendance);
            $corrections->request('edit', $attendance->employee, $attendance->work_date, $attendance, $attributes, $data['reason'], auth()->user());
        }

        Flux::toast(variant: 'success', text: __('Change sent for approval.'));
        $this->form = $this->blankForm();
        $this->editingUuid = null;
        $this->showForm = false;
    }

    public function approveCorrection(string $uuid, AttendanceCorrectionService $corrections): void
    {
        Gate::authorize('create', Attendance::class);
        $correction = AttendanceCorrection::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $corrections->approve($correction, auth()->user());
            Flux::toast(variant: 'success', text: __('Change approved and applied.'));
        } catch (AttendanceException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function rejectCorrection(string $uuid, AttendanceCorrectionService $corrections): void
    {
        Gate::authorize('create', Attendance::class);
        $correction = AttendanceCorrection::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $corrections->reject($correction, auth()->user());
            Flux::toast(variant: 'success', text: __('Change rejected.'));
        } catch (AttendanceException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function toggleApproval(Auditor $auditor): void
    {
        Gate::authorize('create', Attendance::class);

        $tenant = Tenancy::getOrFail();
        $settings = (array) $tenant->settings;
        $enabled = ! (bool) ($settings['attendance']['require_edit_approval'] ?? config('attendance.require_edit_approval', false));
        $settings['attendance'] = [...(array) ($settings['attendance'] ?? []), 'require_edit_approval' => $enabled];

        $tenant->forceFill(['settings' => $settings])->save();
        $auditor->log('attendance.approval_setting_changed', $tenant, ['require_edit_approval' => $enabled]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'employee_id' => '', 'work_date' => now()->toDateString(), 'clock_in_at' => '',
            'clock_out_at' => '', 'break_minutes' => '', 'status' => 'present', 'notes' => '', 'reason' => '',
        ];
    }

    /**
     * New punches and corrections appear without a reload.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return $tenantId === null ? [] : [
            "echo-private:tenant.{$tenantId}.attendance,.attendance.logged" => 'refreshRecords',
        ];
    }

    public function refreshRecords(): void {}

    public function render(): View
    {
        $records = Attendance::query()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->orderByDesc('work_date')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('livewire.attendance.records', [
            'records' => $records,
            'pending' => AttendanceCorrection::query()->where('status', 'pending')->with(['employee:id,first_name,last_name', 'requester:id,name'])->latest('id')->get(),
            'requireApproval' => app(AttendanceCorrectionService::class)->requiresApproval(),
            'employees' => Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }
}
