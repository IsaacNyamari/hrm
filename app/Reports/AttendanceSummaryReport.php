<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;

class AttendanceSummaryReport extends BaseReport
{
    public function key(): string
    {
        return 'attendance-summary';
    }

    public function name(): string
    {
        return 'Attendance summary';
    }

    public function category(): string
    {
        return 'Time';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return ['attendance.view'];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Employee', 'Number', 'Present', 'Late', 'Absent', 'Overtime (hrs)'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $from = $this->date($filters, 'date_from', CarbonImmutable::now()->startOfMonth());
        $to = $this->date($filters, 'date_to', CarbonImmutable::now());

        $records = Attendance::query()
            ->when($from !== null, fn ($q) => $q->whereDate('work_date', '>=', $from->toDateString()))
            ->when($to !== null, fn ($q) => $q->whereDate('work_date', '<=', $to->toDateString()))
            ->get(['employee_id', 'status', 'overtime_minutes'])
            ->groupBy('employee_id');

        $employees = Employee::query()
            ->whereIn('id', $records->keys()->all())
            ->get(['id', 'first_name', 'last_name', 'employee_number'])
            ->keyBy('id');

        $rows = [];
        foreach ($records as $employeeId => $group) {
            $employee = $employees->get($employeeId);
            $name = $employee !== null ? $employee->fullName() : '#'.$employeeId;
            $number = $employee !== null ? $employee->employee_number : '';

            $rows[] = [
                $name,
                $number,
                $group->where('status', AttendanceStatus::Present)->count() + $group->where('status', AttendanceStatus::Late)->count(),
                $group->where('status', AttendanceStatus::Late)->count(),
                $group->where('status', AttendanceStatus::Absent)->count(),
                round($group->sum('overtime_minutes') / 60, 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]));

        return $rows;
    }
}
