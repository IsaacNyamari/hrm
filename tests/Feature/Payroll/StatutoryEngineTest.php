<?php

declare(strict_types=1);

use App\Exceptions\PayrollException;
use App\Models\TaxRule;
use App\Services\Payroll\StatutoryEngine;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = tenantWithRoles(); // ProvisionTenant seeds the KE tax_rules
    app(TenantContext::class)->set($this->tenant);
    $this->engine = app(StatutoryEngine::class);
    $this->payDate = CarbonImmutable::create(2026, 9, 30);
});

it('computes NSSF at 6% capped at the ceiling', function () {
    $lines = collect($this->engine->compute(10_000_000, 10_000_000, 10_000_000, $this->payDate));

    expect($lines->firstWhere('code', 'NSSF')['amount_cents'])->toBe(432_000); // 6% of 7,200,000
});

it('computes SHIF at 2.75% of gross with a floor', function () {
    $big = collect($this->engine->compute(10_000_000, 10_000_000, 10_000_000, $this->payDate));
    $small = collect($this->engine->compute(500_000, 500_000, 500_000, $this->payDate));

    expect($big->firstWhere('code', 'SHIF')['amount_cents'])->toBe(275_000)
        ->and($small->firstWhere('code', 'SHIF')['amount_cents'])->toBe(30_000); // minimum
});

it('computes the Housing Levy at 1.5% of gross', function () {
    $lines = collect($this->engine->compute(10_000_000, 10_000_000, 10_000_000, $this->payDate));

    expect($lines->firstWhere('code', 'HOUSING_LEVY')['amount_cents'])->toBe(150_000);
});

it('computes PAYE on income net of the pre-tax statutory reliefs, minus personal relief', function () {
    $lines = collect($this->engine->compute(10_000_000, 10_000_000, 10_000_000, $this->payDate));

    // taxable for PAYE = 10,000,000 - (432,000 + 275,000 + 150,000) = 9,143,000
    // bands: 240,000 + 208,325 + 1,772,910 = 2,221,235 ; less relief 240,000
    expect($lines->firstWhere('code', 'PAYE')['amount_cents'])->toBe(1_981_235);
});

it('returns zero PAYE for a low earner after relief', function () {
    // gross 20,000 -> after statutory ~ 18,750 -> band tax 1,875 < relief 2,400 -> 0
    $lines = collect($this->engine->compute(2_000_000, 2_000_000, 2_000_000, $this->payDate));

    expect($lines->firstWhere('code', 'PAYE')['amount_cents'])->toBe(0);
});

it('fails clearly when no rule is in force for the pay date', function () {
    TaxRule::query()->update(['effective_from' => '2027-01-01']);
    app(TenantContext::class)->set($this->tenant->fresh());

    $this->engine->compute(10_000_000, 10_000_000, 10_000_000, $this->payDate);
})->throws(PayrollException::class);
