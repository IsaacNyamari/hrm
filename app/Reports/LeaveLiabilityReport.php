<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\LeaveBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What unused paid leave is worth: remaining days × a daily rate of monthly
 * basic ÷ 22 working days. An estimate for provisioning, not a payout figure.
 */
class LeaveLiabilityReport extends BaseReport
{
    private const WORKING_DAYS_PER_MONTH = 22;

    public function key(): string
    {
        return 'leave-liability';
    }

    public function name(): string
    {
        return 'Leave liability';
    }

    public function category(): string
    {
        return 'People';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return ['payroll.view', 'leave.view'];
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
        return ['Employee', 'Leave type', 'Remaining days', 'Daily rate', 'Liability'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        return $this->entries($filters)->map(fn (array $e): array => [$e['employee'], $e['type'], $e['days'], $this->money($e['rate']), $this->money($e['liability'])])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $entries = $this->entries($filters);

        return [
            'Remaining paid leave days' => round($entries->sum('days'), 2),
            'Estimated liability' => $this->money((int) $entries->sum('liability')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{employee: string, type: string, days: float, rate: int, liability: int}>
     */
    private function entries(array $filters): Collection
    {
        $year = $this->int($filters, 'year') ?? CarbonImmutable::now()->year;

        return LeaveBalance::query()
            ->where('year', $year)
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', true))
            ->whereHas('employee', fn ($q) => $q->active())
            ->with(['employee:id,first_name,last_name,salary_amount_cents,pay_frequency', 'leaveType:id,name'])
            ->get()
            ->map(function (LeaveBalance $balance): ?array {
                $days = round($balance->remainingDays(), 2);
                $employee = $balance->employee;

                if ($days <= 0 || $employee === null || $employee->pay_frequency === 'hourly') {
                    return null;
                }

                $rate = (int) round(((int) $employee->salary_amount_cents) / self::WORKING_DAYS_PER_MONTH);

                return [
                    'employee' => $employee->fullName(),
                    'type' => (string) ($balance->leaveType?->name ?? '#'.$balance->leave_type_id),
                    'days' => $days,
                    'rate' => $rate,
                    'liability' => (int) round($days * $rate),
                ];
            })
            ->filter()
            ->sortBy('employee')
            ->values();
    }
}
