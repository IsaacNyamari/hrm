<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An attendance punch or correction was recorded (web clock, mobile/API,
 * biometric device or HR edit). HR staff of the organization see it live on
 * the attendance screens with a toast; the employee's own screens refresh.
 */
class AttendanceLogged implements ShouldBroadcastNow
{
    use Dispatchable;

    public const CLOCK_IN = 'clock_in';

    public const CLOCK_OUT = 'clock_out';

    public const BREAK_START = 'break_start';

    public const BREAK_END = 'break_end';

    public const CORRECTED = 'corrected';

    public function __construct(
        public readonly int $tenantId,
        public readonly ?int $employeeUserId,
        public readonly string $employeeName,
        public readonly string $action,
        public readonly string $time,
        public readonly string $status,
        public readonly string $source,
    ) {}

    public static function for(Employee $employee, Attendance $attendance, string $action): self
    {
        $at = match ($action) {
            self::CLOCK_IN => $attendance->clock_in_at,
            self::CLOCK_OUT => $attendance->clock_out_at,
            default => null,
        } ?? now();

        return new self(
            tenantId: (int) $employee->tenant_id,
            employeeUserId: $employee->user_id,
            employeeName: $employee->fullName(),
            action: $action,
            time: $at->format('H:i'),
            status: $attendance->status->label(),
            source: $attendance->source?->value ?? 'web',
        );
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('tenant.'.$this->tenantId.'.attendance')];

        if ($this->employeeUserId !== null) {
            $channels[] = new PrivateChannel('App.Models.User.'.$this->employeeUserId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'attendance.logged';
    }

    /**
     * @return array{employee: string, action: string, message: string, time: string, status: string, source: string}
     */
    public function broadcastWith(): array
    {
        return [
            'employee' => $this->employeeName,
            'action' => $this->action,
            'message' => $this->message(),
            'time' => $this->time,
            'status' => $this->status,
            'source' => $this->source,
        ];
    }

    /**
     * e.g. "Jane Doe clocked in at 08:12 (Late)".
     */
    public function message(): string
    {
        $what = match ($this->action) {
            self::CLOCK_IN => "clocked in at {$this->time}",
            self::CLOCK_OUT => "clocked out at {$this->time}",
            self::BREAK_START => "started a break at {$this->time}",
            self::BREAK_END => "ended a break at {$this->time}",
            default => 'had their attendance updated',
        };

        $status = in_array($this->action, [self::CLOCK_IN, self::CLOCK_OUT], true) && $this->status !== 'Present'
            ? " ({$this->status})"
            : '';

        return "{$this->employeeName} {$what}{$status}";
    }
}
