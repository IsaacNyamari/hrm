<?php

declare(strict_types=1);

use App\Actions\Tenancy\ProvisionTenantHolidays;
use App\Models\Holiday;
use App\Support\TenantContext;

it('computes Kenya public holidays including the moving Easter dates', function () {
    $dates = collect(app(ProvisionTenantHolidays::class)->forYear(2026))
        ->mapWithKeys(fn (array $holiday): array => [$holiday[0] => $holiday[1]->toDateString()]);

    expect($dates['Good Friday'])->toBe('2026-04-03')
        ->and($dates['Easter Monday'])->toBe('2026-04-06')
        ->and($dates['Madaraka Day'])->toBe('2026-06-01')
        ->and($dates['Mashujaa Day'])->toBe('2026-10-20')
        ->and($dates['Jamhuri Day'])->toBe('2026-12-12');
});

it('observes a holiday that falls on a Sunday on the next working day', function () {
    $dates = collect(app(ProvisionTenantHolidays::class)->forYear(2023))
        ->mapWithKeys(fn (array $holiday): array => [$holiday[0] => $holiday[1]->toDateString()]);

    expect($dates["New Year's Day"])->toBe('2023-01-01')
        ->and($dates["New Year's Day (observed)"])->toBe('2023-01-02');
});

it('seeds holidays for a new organization once and does not duplicate them', function () {
    $tenant = tenantWithRoles();
    app(TenantContext::class)->set($tenant);

    $count = Holiday::query()->count();
    app(ProvisionTenantHolidays::class)->handle($tenant);

    expect($count)->toBeGreaterThan(15)
        ->and(Holiday::query()->count())->toBe($count);
});
