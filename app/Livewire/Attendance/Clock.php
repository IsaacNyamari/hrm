<?php

declare(strict_types=1);

namespace App\Livewire\Attendance;

use App\Enums\AttendanceSource;
use App\Exceptions\AttendanceException;
use App\Livewire\Concerns\BindsTenant;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Clock extends Component
{
    use BindsTenant;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?string $error = null;

    private function service(): AttendanceService
    {
        return app(AttendanceService::class);
    }

    private function punch(): PunchData
    {
        $userId = auth()->id();

        return new PunchData(
            source: AttendanceSource::Web,
            ip: request()->ip(),
            latitude: $this->latitude,
            longitude: $this->longitude,
            recordedBy: is_int($userId) ? $userId : null,
        );
    }

    public function clockIn(): void
    {
        $this->run(fn ($e) => $this->service()->clockIn($e, $this->punch()));
    }

    public function clockOut(): void
    {
        $this->run(fn ($e) => $this->service()->clockOut($e, $this->punch()));
    }

    public function toggleBreak(): void
    {
        $this->run(function ($employee): void {
            $attendance = $this->service()->todayFor($employee);

            $attendance?->isOnBreak()
                ? $this->service()->endBreak($employee, $this->punch())
                : $this->service()->startBreak($employee, $this->punch());
        });
    }

    private function run(callable $action): void
    {
        $this->error = null;
        $employee = auth()->user()?->employee;

        if ($employee === null) {
            $this->error = 'Your account is not linked to an employee record.';

            return;
        }

        try {
            $action($employee);
        } catch (AttendanceException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Refresh when this employee's attendance changes elsewhere (a biometric
     * device, an HR correction or another open tab).
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = auth()->id();

        return $id === null ? [] : ["echo-private:App.Models.User.{$id},.attendance.logged" => 'refreshOwnAttendance'];
    }

    public function refreshOwnAttendance(): void {}

    public function render(): View
    {
        $employee = auth()->user()?->employee;
        $attendance = $employee !== null ? $this->service()->todayFor($employee) : null;

        return view('livewire.attendance.clock', [
            'hasEmployee' => $employee !== null,
            'attendance' => $attendance,
            'clockedIn' => $attendance?->isClockedIn() ?? false,
            'onBreak' => $attendance?->isOnBreak() ?? false,
            'clockedOut' => $attendance?->clock_out_at !== null,
        ]);
    }
}
