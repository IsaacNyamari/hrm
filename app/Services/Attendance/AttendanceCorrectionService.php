<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Facades\Tenancy;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auditor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Maker-checker for manual attendance changes. When an organization turns on
 * approval, an add, edit or delete is recorded as a request and only applied
 * once a different person approves it.
 */
class AttendanceCorrectionService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly Auditor $auditor,
    ) {}

    public function requiresApproval(?Tenant $tenant = null): bool
    {
        $tenant ??= Tenancy::getOrFail();

        return (bool) (((array) $tenant->settings)['attendance']['require_edit_approval'] ?? config('attendance.require_edit_approval', false));
    }

    /**
     * @param  array<string, mixed>|null  $proposed
     */
    public function request(string $action, Employee $employee, CarbonImmutable $date, ?Attendance $existing, ?array $proposed, string $reason, User $by): AttendanceCorrection
    {
        $correction = AttendanceCorrection::query()->create([
            'employee_id' => $employee->getKey(),
            'attendance_id' => $existing?->getKey(),
            'action' => $action,
            'work_date' => $date->toDateString(),
            'proposed' => $proposed,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $by->getKey(),
        ]);

        $this->auditor->log('attendance.correction_requested', $correction, ['action' => $action, 'reason' => $reason, 'before' => $existing === null ? null : self::snapshot($existing), 'proposed' => $proposed]);

        return $correction;
    }

    public function approve(AttendanceCorrection $correction, User $approver): AttendanceCorrection
    {
        $this->guard($correction, $approver);

        return DB::transaction(function () use ($correction, $approver): AttendanceCorrection {
            $employee = $correction->employee;
            $existing = $correction->attendance_id === null ? null : Attendance::query()->find($correction->attendance_id);
            $before = $existing === null ? null : self::snapshot($existing);

            if ($correction->action === 'delete') {
                $existing?->delete();
            } else {
                $record = $this->attendance->recordManual($employee, $correction->work_date, (array) $correction->proposed, (int) $correction->requested_by);
                $existing = $record;
            }

            $correction->forceFill(['status' => 'approved', 'decided_by' => $approver->getKey(), 'decided_at' => now()])->save();

            $this->auditor->log('attendance.correction_approved', $correction, [
                'action' => $correction->action, 'reason' => $correction->reason, 'before' => $before,
                'after' => $correction->action === 'delete' || $existing === null ? null : self::snapshot($existing->refresh()),
            ]);

            return $correction;
        });
    }

    public function reject(AttendanceCorrection $correction, User $approver, ?string $note = null): AttendanceCorrection
    {
        $this->guard($correction, $approver);

        $correction->forceFill(['status' => 'rejected', 'decided_by' => $approver->getKey(), 'decided_at' => now(), 'decision_note' => $note])->save();
        $this->auditor->log('attendance.correction_rejected', $correction, ['action' => $correction->action, 'note' => $note]);

        return $correction;
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Attendance $attendance): array
    {
        return [
            'employee_id' => $attendance->employee_id,
            'work_date' => $attendance->work_date->toDateString(),
            'clock_in_at' => $attendance->clock_in_at?->toDateTimeString(),
            'clock_out_at' => $attendance->clock_out_at?->toDateTimeString(),
            'break_minutes' => $attendance->break_minutes,
            'status' => $attendance->status->value,
            'notes' => $attendance->notes,
        ];
    }

    private function guard(AttendanceCorrection $correction, User $approver): void
    {
        if (! $correction->isPending()) {
            throw new AttendanceException('This request has already been decided.');
        }

        if ($correction->requested_by === $approver->getKey()) {
            throw new AttendanceException('A different person must approve an attendance change you requested.');
        }
    }
}
