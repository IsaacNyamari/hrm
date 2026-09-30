<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Seeds Kenya's public holidays (Public Holidays under the Public Holidays
 * Act, Cap 110) for a range of years so leave and attendance skip them.
 * Islamic holidays (Idd-ul-Fitr) are announced each year by the government,
 * so they are not seeded; add them under Holidays once gazetted.
 * Idempotent: a holiday already present on a date is left alone.
 */
class ProvisionTenantHolidays
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant, ?int $fromYear = null, ?int $toYear = null): void
    {
        $fromYear ??= CarbonImmutable::now()->year;
        $toYear ??= $fromYear + 1;

        $this->context->run($tenant, function () use ($tenant, $fromYear, $toYear): void {
            for ($year = $fromYear; $year <= $toYear; $year++) {
                foreach ($this->forYear($year) as [$name, $date]) {
                    if (Holiday::query()->where('tenant_id', $tenant->getKey())->whereDate('date', $date->toDateString())->where('name', $name)->exists()) {
                        continue;
                    }

                    Holiday::query()->create([
                        'tenant_id' => $tenant->getKey(),
                        'date' => $date->toDateString(),
                        'name' => $name,
                        'type' => HolidayType::Public,
                        'is_recurring' => false,
                        'is_active' => true,
                    ]);
                }
            }
        });
    }

    /**
     * @return list<array{0: string, 1: CarbonImmutable}>
     */
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        $holidays = [
            ["New Year's Day", CarbonImmutable::create($year, 1, 1)],
            ['Good Friday', $easter->subDays(2)],
            ['Easter Monday', $easter->addDay()],
            ['Labour Day', CarbonImmutable::create($year, 5, 1)],
            ['Madaraka Day', CarbonImmutable::create($year, 6, 1)],
            ['Mazingira Day', CarbonImmutable::create($year, 10, 10)],
            ['Mashujaa Day', CarbonImmutable::create($year, 10, 20)],
            ['Jamhuri Day', CarbonImmutable::create($year, 12, 12)],
            ['Christmas Day', CarbonImmutable::create($year, 12, 25)],
            ['Boxing Day', CarbonImmutable::create($year, 12, 26)],
        ];

        // A public holiday that falls on a Sunday is observed on the Monday.
        $extra = [];

        foreach ($holidays as [$name, $date]) {
            if ($date->isSunday()) {
                $monday = $date->addDay();
                $taken = array_filter($holidays, fn (array $h): bool => $h[1]->isSameDay($monday));
                $extra[] = $taken === [] ? [$name.' (observed)', $monday] : [$name.' (observed)', $monday->addDay()];
            }
        }

        return [...$holidays, ...$extra];
    }

    /**
     * Anonymous Gregorian algorithm (Meeus/Jones/Butcher), so no PHP calendar
     * extension is required.
     */
    private function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
