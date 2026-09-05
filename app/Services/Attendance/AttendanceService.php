<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\Attendance\PunchData;
use App\Support\Geo;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private readonly TenantContext $context) {}

    public function todayFor(Employee $employee, ?CarbonImmutable $date = null): ?Attendance
    {
        return $employee->attendances()
            ->whereDate('work_date', ($date ?? CarbonImmutable::now())->toDateString())
            ->first();
    }

    public function clockIn(Employee $employee, PunchData $punch): Attendance
    {
        return DB::transaction(function () use ($employee, $punch): Attendance {
            $at = $punch->time();
            $existing = $this->todayFor($employee, $at);

            if ($existing !== null && $existing->clock_in_at !== null
                && ! $this->setting('allow_multiple_clock_ins', false)) {
                throw $existing->clock_out_at === null
                    ? AttendanceException::alreadyClockedIn()
                    : AttendanceException::alreadyClockedOut();
            }

            $this->assertWithinGeofence($employee, $punch);

            $shift = $this->resolveShift($employee, $at);
            $status = $this->arrivalStatus($shift, $at);

            $attendance = $existing ?? new Attendance([
                'employee_id' => $employee->getKey(),
                'work_date' => $at->toDateString(),
            ]);

            $attendance->fill([
                'shift_id' => $shift?->getKey(),
                'clock_in_at' => $at,
                'clock_in_ip' => $punch->ip,
                'clock_in_lat' => $punch->latitude,
                'clock_in_lng' => $punch->longitude,
                'clock_in_location' => $punch->location,
                'status' => $status,
                'source' => $punch->source,
                'recorded_by' => $punch->recordedBy,
            ])->save();

            return $attendance;
        });
    }

    public function clockOut(Employee $employee, PunchData $punch): Attendance
    {
        return DB::transaction(function () use ($employee, $punch): Attendance {
            $at = $punch->time();
            $attendance = $this->todayFor($employee, $at);

            if ($attendance === null || $attendance->clock_in_at === null) {
                throw AttendanceException::notClockedIn();
            }

            if ($attendance->clock_out_at !== null) {
                throw AttendanceException::alreadyClockedOut();
            }

            $this->assertWithinGeofence($employee, $punch);

            if ($attendance->break_started_at !== null) {
                $this->closeBreak($attendance, $at);
            }

            $shift = $attendance->shift;
            $breakDeduction = max($attendance->break_minutes, $this->scheduledBreakMinutes($shift));
            $worked = max(0, (int) $attendance->clock_in_at->diffInMinutes($at) - $breakDeduction);

            $attendance->fill([
                'clock_out_at' => $at,
                'clock_out_ip' => $punch->ip,
                'clock_out_lat' => $punch->latitude,
                'clock_out_lng' => $punch->longitude,
                'clock_out_location' => $punch->location,
                'worked_minutes' => $worked,
                'overtime_minutes' => $this->overtimeMinutes($shift, $worked),
                'status' => $this->departureStatus($attendance->status, $shift, $at),
            ])->save();

            return $attendance;
        });
    }

    public function startBreak(Employee $employee, PunchData $punch): Attendance
    {
        $attendance = $this->todayFor($employee, $punch->time());

        if ($attendance === null || ! $attendance->isClockedIn()) {
            throw AttendanceException::notClockedIn();
        }

        if ($attendance->break_started_at !== null) {
            throw AttendanceException::alreadyOnBreak();
        }

        DB::transaction(function () use ($attendance, $punch): void {
            $attendance->forceFill(['break_started_at' => $punch->time()])->save();
            $attendance->breaks()->create(['started_at' => $punch->time()]);
        });

        return $attendance->refresh();
    }

    public function endBreak(Employee $employee, PunchData $punch): Attendance
    {
        $attendance = $this->todayFor($employee, $punch->time());

        if ($attendance === null || $attendance->break_started_at === null) {
            throw AttendanceException::notOnBreak();
        }

        DB::transaction(fn () => $this->closeBreak($attendance, $punch->time()));

        return $attendance->refresh();
    }

    /**
     * Admin manual create/correct. $attributes may include clock_in_at,
     * clock_out_at, status, notes, break_minutes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordManual(Employee $employee, CarbonImmutable $date, array $attributes, int $recordedBy): Attendance
    {
        return DB::transaction(function () use ($employee, $date, $attributes, $recordedBy): Attendance {
            $attendance = $this->todayFor($employee, $date) ?? new Attendance([
                'employee_id' => $employee->getKey(),
                'work_date' => $date->toDateString(),
            ]);

            $shift = $this->resolveShift($employee, $date);
            $attendance->fill($attributes);
            $attendance->shift_id ??= $shift?->getKey();
            $attendance->source = AttendanceSource::Admin;
            $attendance->recorded_by = $recordedBy;
            $attendance->status ??= AttendanceStatus::Present;
            $attendance->break_minutes ??= 0;

            if ($attendance->clock_in_at !== null && $attendance->clock_out_at !== null) {
                $breakDeduction = max((int) $attendance->break_minutes, $this->scheduledBreakMinutes($shift));
                $worked = max(0, (int) $attendance->clock_in_at->diffInMinutes($attendance->clock_out_at) - $breakDeduction);
                $attendance->worked_minutes = $worked;
                $attendance->overtime_minutes = $this->overtimeMinutes($shift, $worked);
            }

            $attendance->save();

            return $attendance->refresh();
        });
    }

    public function resolveShift(Employee $employee, CarbonImmutable $date): ?Shift
    {
        $assignment = $employee->shiftAssignments()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($assignment !== null) {
            return $assignment->shift;
        }

        return Shift::query()->where('is_default', true)->where('is_active', true)->first();
    }

    private function closeBreak(Attendance $attendance, CarbonImmutable $at): void
    {
        $open = $attendance->breaks()->whereNull('ended_at')->latest('started_at')->first();
        $startedAt = $open !== null ? $open->started_at : ($attendance->break_started_at ?? $at);
        $minutes = max(0, (int) $startedAt->diffInMinutes($at));

        if ($open !== null) {
            $open->forceFill(['ended_at' => $at, 'minutes' => $minutes])->save();
        }

        $attendance->forceFill([
            'break_started_at' => null,
            'break_minutes' => $attendance->break_minutes + $minutes,
        ])->save();
    }

    private function assertWithinGeofence(Employee $employee, PunchData $punch): void
    {
        if (! $this->setting('geofence_required', config('attendance.geofence_required'))) {
            return;
        }

        $branch = $employee->branch;

        if ($branch === null || $branch->latitude === null || $branch->longitude === null) {
            return; // nothing to fence against
        }

        if (! $punch->hasCoordinates()) {
            throw AttendanceException::coordinatesRequired();
        }

        $radius = $branch->geofence_radius_meters ?? (int) config('attendance.default_geofence_radius_meters');
        $distance = Geo::distanceMeters(
            (float) $branch->latitude,
            (float) $branch->longitude,
            (float) $punch->latitude,
            (float) $punch->longitude,
        );

        if ($distance > $radius) {
            throw AttendanceException::outsideGeofence();
        }
    }

    private function arrivalStatus(?Shift $shift, CarbonImmutable $at): AttendanceStatus
    {
        if ($shift === null) {
            return AttendanceStatus::Present;
        }

        [$h, $m] = array_map('intval', explode(':', $shift->start_time));
        $start = $at->setTime($h, $m)->addMinutes($shift->grace_minutes);

        return $at->greaterThan($start) ? AttendanceStatus::Late : AttendanceStatus::Present;
    }

    private function departureStatus(AttendanceStatus $current, ?Shift $shift, CarbonImmutable $at): AttendanceStatus
    {
        if ($shift === null || $current === AttendanceStatus::Late) {
            return $current;
        }

        [$h, $m] = array_map('intval', explode(':', $shift->end_time));
        $end = $at->setTime($h, $m);

        if ($shift->is_night_shift && $at->hour < 12) {
            $end = $end->subDay();
        }

        return $at->lessThan($end) ? AttendanceStatus::EarlyDeparture : $current;
    }

    private function scheduledBreakMinutes(?Shift $shift): int
    {
        if ($shift === null) {
            return 0;
        }

        return $shift->break_minutes;
    }

    private function overtimeMinutes(?Shift $shift, int $worked): int
    {
        if ($shift === null) {
            return 0;
        }

        $scheduled = $shift->scheduledMinutes();
        $threshold = $shift->overtime_after_minutes ?? 0;

        return $worked > $scheduled + $threshold ? $worked - $scheduled : 0;
    }

    private function setting(string $key, mixed $default): mixed
    {
        $tenant = $this->context->get();
        $value = $tenant?->settings['attendance'][$key] ?? null;

        return $value ?? $default;
    }
}
