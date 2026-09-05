<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Models\Holiday;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Counts working days between two dates for the bound tenant, excluding weekend
 * days (tenant-configurable) and active holidays (recurring holidays expand to
 * each year in range).
 */
class WorkingDayCalculator
{
    public function __construct(private readonly TenantContext $context) {}

    public function countWorkingDays(
        CarbonImmutable $start,
        CarbonImmutable $end,
        bool $halfDayStart = false,
        bool $halfDayEnd = false,
    ): float {
        $weekend = $this->weekendDays();
        $holidays = $this->holidayDates($start, $end);

        $days = 0.0;
        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($this->isNonWorking($day, $weekend, $holidays)) {
                continue;
            }
            $days += 1.0;
        }

        if ($days >= 1.0 && $halfDayStart && ! $this->isNonWorking($start, $weekend, $holidays)) {
            $days -= 0.5;
        }

        if ($days >= 1.0 && $halfDayEnd && ! $start->equalTo($end) && ! $this->isNonWorking($end, $weekend, $holidays)) {
            $days -= 0.5;
        }

        return $days;
    }

    /**
     * @param  array<int, int>  $weekend
     * @param  array<int, string>  $holidays
     */
    public function isNonWorking(CarbonImmutable $day, array $weekend, array $holidays): bool
    {
        return in_array($day->isoWeekday(), $weekend, true)
            || in_array($day->toDateString(), $holidays, true);
    }

    /**
     * @return array<int, int>
     */
    public function weekendDays(): array
    {
        $tenant = $this->context->get();
        $configured = $tenant?->settings['leave']['weekend_days'] ?? null;

        /** @var array<int, int> $days */
        $days = is_array($configured) ? $configured : config('leave.weekend_days');

        return array_map('intval', $days);
    }

    /**
     * @return array<int, string>
     */
    public function holidayDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dates = [];

        Holiday::query()->where('is_active', true)->get(['date', 'is_recurring'])
            ->each(function (Holiday $holiday) use (&$dates, $start, $end): void {
                if (! $holiday->is_recurring) {
                    $dates[] = $holiday->date->toDateString();

                    return;
                }

                foreach (range($start->year, $end->year) as $year) {
                    $dates[] = $holiday->date->setDate($year, (int) $holiday->date->month, (int) $holiday->date->day)->toDateString();
                }
            });

        return array_values(array_unique($dates));
    }
}
