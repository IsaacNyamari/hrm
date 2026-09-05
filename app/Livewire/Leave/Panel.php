<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Exceptions\LeaveException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
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

    public ?string $error = null;

    public ?string $success = null;

    public function submit(LeaveService $service): void
    {
        $this->error = null;
        $this->success = null;

        $this->validate([
            'leaveTypeUuid' => ['required'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = auth()->user()?->employee;

        if ($employee === null) {
            $this->error = 'Your account is not linked to an employee record.';

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

            $this->success = 'Leave request submitted.';
            $this->reset(['startDate', 'endDate', 'reason', 'halfDayStart', 'halfDayEnd']);
        } catch (LeaveException $e) {
            $this->error = $e->getMessage();
        } catch (ValidationException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(LeaveService $service): View
    {
        $employee = auth()->user()?->employee;
        $year = CarbonImmutable::now()->year;

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

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

        return view('livewire.leave.panel', [
            'hasEmployee' => $employee !== null,
            'leaveTypes' => $types,
            'balances' => $balances,
            'requests' => $requests,
        ]);
    }
}
