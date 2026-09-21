<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Services\Leave\WorkingDayCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Summarises an employee's attendance for a payroll period into the numbers
 * payroll needs:
 *
 * - payable days: each working day earns 1 when present (late, early
 *   departure and remote count as present), ½ for a half day, and 1 for
 *   approved *paid* leave. Weekends and public holidays are not working days,
 *   so they never reduce pay.
 * - overtime hours on working days (from the attendance record), and all hours
 *   worked on rest days / public holidays (paid at the rest-day rate).
 */
class AttendancePayCalculator
{
    private const FULL_DAY = [
        AttendanceStatus::Present,
        AttendanceStatus::Late,
        AttendanceStatus::EarlyDeparture,
        AttendanceStatus::RemoteWork,
    ];

    public function __construct(private readonly WorkingDayCalculator $calendar) {}

    /**
     * @return array{working_days: float, payable_days: float, present_days: float, paid_leave_days: float, absent_days: float, worked_hours: float, regular_hours: float, overtime_hours: float, rest_day_hours: float}
     */
    public function summarize(Employee $employee, PayrollPeriod $period): array
    {
        return $this->summarizeRange($employee, $period->start_date, $period->end_date);
    }

    /**
     * Same as {@see self::summarize()} for an arbitrary date range, e.g. month
     * to date or up to an employee's exit date.
     *
     * @return array{working_days: float, payable_days: float, present_days: float, paid_leave_days: float, absent_days: float, worked_hours: float, regular_hours: float, overtime_hours: float, rest_day_hours: float}
     */
    public function summarizeRange(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();

        $workingDates = $this->workingDates($start, $end);

        $attendanceCredit = [];
        $workedMinutes = 0;
        $overtimeMinutes = 0;
        $restDayMinutes = 0;

        $records = Attendance::query()
            ->where('employee_id', $employee->getKey())
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get(['work_date', 'status', 'worked_minutes', 'overtime_minutes']);

        foreach ($records as $record) {
            $date = CarbonImmutable::parse($record->work_date)->toDateString();
            $worked = (int) ($record->worked_minutes ?? 0);
            $workedMinutes += $worked;

            if (! isset($workingDates[$date])) {
                // Rest day or public holiday: every minute worked is overtime.
                if ($worked > 0 && $record->status !== AttendanceStatus::Absent) {
                    $restDayMinutes += $worked;
                }

                continue;
            }

            $overtimeMinutes += (int) $record->overtime_minutes;

            $attendanceCredit[$date] = match (true) {
                in_array($record->status, self::FULL_DAY, true) => 1.0,
                $record->status === AttendanceStatus::HalfDay => 0.5,
                default => 0.0,
            };
        }

        $leaveCredit = $this->paidLeaveCredit($employee, $start, $end, $workingDates);

        $payable = 0.0;
        $present = 0.0;
        $paidLeave = 0.0;

        foreach (array_keys($workingDates) as $date) {
            $attended = $attendanceCredit[$date] ?? 0.0;
            $leave = $leaveCredit[$date] ?? 0.0;
            $credit = min(1.0, $attended + $leave);

            $payable += $credit;
            $present += $attended;
            $paidLeave += max(0.0, $credit - $attended);
        }

        $workingDays = (float) count($workingDates);

        return [
            'working_days' => $workingDays,
            'payable_days' => round($payable, 2),
            'present_days' => round($present, 2),
            'paid_leave_days' => round($paidLeave, 2),
            'absent_days' => round(max(0.0, $workingDays - $payable), 2),
            'worked_hours' => round($workedMinutes / 60, 2),
            'regular_hours' => round(max(0, $workedMinutes - $overtimeMinutes - $restDayMinutes) / 60, 2),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'rest_day_hours' => round($restDayMinutes / 60, 2),
        ];
    }

    /**
     * Number of working days (excluding weekends and public holidays) in a range.
     */
    public function workingDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return count($this->workingDates($start->startOfDay(), $end->startOfDay()));
    }

    /**
     * @return array<string, bool> date => true for every working day in the range
     */
    private function workingDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($end->lessThan($start)) {
            return [];
        }

        $weekend = $this->calendar->weekendDays();
        $holidays = $this->calendar->holidayDates($start, $end);

        $workingDates = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $day = CarbonImmutable::parse($day);
            if (! $this->calendar->isNonWorking($day, $weekend, $holidays)) {
                $workingDates[$day->toDateString()] = true;
            }
        }

        return $workingDates;
    }

    /**
     * Credit per working date from approved leave of a paid leave type
     * (½ on a half-day start/end).
     *
     * @param  array<string, bool>  $workingDates
     * @return array<string, float>
     */
    private function paidLeaveCredit(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, array $workingDates): array
    {
        $credit = [];

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->getKey())
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', true))
            ->get(['start_date', 'end_date', 'half_day_start', 'half_day_end']);

        foreach ($requests as $request) {
            $from = CarbonImmutable::parse($request->start_date)->startOfDay();
            $to = CarbonImmutable::parse($request->end_date)->startOfDay();

            foreach (CarbonPeriod::create($from->max($start), $to->min($end)) as $day) {
                $date = CarbonImmutable::parse($day)->toDateString();

                if (! isset($workingDates[$date])) {
                    continue;
                }

                $isHalf = ($request->half_day_start && $date === $from->toDateString())
                    || ($request->half_day_end && $date === $to->toDateString());

                $credit[$date] = min(1.0, ($credit[$date] ?? 0.0) + ($isHalf ? 0.5 : 1.0));
            }
        }

        return $credit;
    }
}
