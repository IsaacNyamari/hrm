<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveBalance;
use Carbon\CarbonImmutable;

class LeaveLedgerReport extends BaseReport
{
    public function key(): string
    {
        return 'leave-ledger';
    }

    public function name(): string
    {
        return 'Leave balances';
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
        return ['leave.view'];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'year', 'label' => 'Leave year', 'type' => 'number'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Employee', 'Leave type', 'Allocated', 'Carried', 'Used', 'Pending', 'Remaining'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $year = $this->int($filters, 'year') ?? CarbonImmutable::now()->year;

        return LeaveBalance::query()
            ->where('year', $year)
            ->with(['employee:id,first_name,last_name', 'leaveType:id,name'])
            ->get()
            ->sortBy(fn (LeaveBalance $b): string => (string) data_get($b, 'employee.first_name'))
            ->map(fn (LeaveBalance $b): array => [
                (string) (data_get($b, 'employee') !== null ? $b->employee->fullName() : '#'.$b->employee_id),
                (string) (data_get($b, 'leaveType.name') ?? '#'.$b->leave_type_id),
                (float) $b->allocated_days,
                (float) $b->carried_forward_days,
                (float) $b->used_days,
                (float) $b->pending_days,
                round($b->remainingDays(), 2),
            ])
            ->values()
            ->all();
    }
}
