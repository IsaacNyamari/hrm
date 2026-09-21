<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\Attendance\AttendanceService;
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
        ];
        $this->showForm = true;
    }

    public function save(AttendanceService $service): void
    {
        $data = $this->validate([
            'form.employee_id' => ['required', 'integer'],
            'form.work_date' => ['required', 'date'],
            'form.clock_in_at' => ['nullable', 'date'],
            'form.clock_out_at' => ['nullable', 'date', 'after_or_equal:form.clock_in_at'],
            'form.break_minutes' => ['nullable', 'integer', 'min:0'],
            'form.status' => ['required', 'string'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ])['form'];

        $attributes = array_filter([
            'clock_in_at' => $data['clock_in_at'] !== '' ? $data['clock_in_at'] : null,
            'clock_out_at' => $data['clock_out_at'] !== '' ? $data['clock_out_at'] : null,
            'break_minutes' => $data['break_minutes'] !== '' ? (int) $data['break_minutes'] : null,
            'status' => AttendanceStatus::from($data['status']),
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ], static fn ($v): bool => $v !== null);

        if ($this->editingUuid === null) {
            Gate::authorize('create', Attendance::class);
            $employee = Employee::query()->findOrFail((int) $data['employee_id']);
            $service->recordManual($employee, CarbonImmutable::parse($data['work_date']), $attributes, (int) auth()->id());
            Flux::toast(variant: 'success', text: __('Attendance record added.'));
        } else {
            $attendance = Attendance::query()->where('uuid', $this->editingUuid)->firstOrFail();
            Gate::authorize('update', $attendance);
            $service->recordManual($attendance->employee, $attendance->work_date, $attributes, (int) auth()->id());
            Flux::toast(variant: 'success', text: __('Attendance record updated.'));
        }

        $this->form = $this->blankForm();
        $this->editingUuid = null;
        $this->showForm = false;
    }

    public function remove(string $uuid): void
    {
        $attendance = Attendance::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $attendance);

        $attendance->delete();
        Flux::modal('delete-attendance-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Attendance record deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'employee_id' => '', 'work_date' => now()->toDateString(), 'clock_in_at' => '',
            'clock_out_at' => '', 'break_minutes' => '', 'status' => 'present', 'notes' => '',
        ];
    }

    public function render(): View
    {
        $records = Attendance::query()
            ->with(['employee:id,first_name,last_name,employee_number'])
            ->orderByDesc('work_date')
            ->paginate(20);

        return view('livewire.attendance.records', [
            'records' => $records,
            'employees' => Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }
}
