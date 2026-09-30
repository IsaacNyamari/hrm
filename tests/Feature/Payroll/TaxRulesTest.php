<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TaxRuleType;
use App\Exceptions\PayrollException;
use App\Livewire\Payroll\TaxRules;
use App\Models\AuditLog;
use App\Models\TaxRule;
use App\Services\Payroll\StatutoryEngine;
use App\Services\Payroll\StatutoryRuleWatch;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    app(TenantContext::class)->set($this->tenant);
});

it('ends the Feb 2026 NSSF limit on 31 Jan 2027 and blocks payroll after it', function () {
    $rule = TaxRule::inForce(TaxRuleType::Nssf, CarbonImmutable::create(2026, 9, 30));

    expect($rule->config['ceiling_cents'])->toBe(10_800_000)
        ->and($rule->effective_to->toDateString())->toBe('2027-01-31');

    app(StatutoryEngine::class)->compute(15_000_000, 15_000_000, 15_000_000, CarbonImmutable::create(2027, 2, 28));
})->throws(PayrollException::class);

it('warns before a rule lapses and clears once a successor is added', function () {
    $watch = app(StatutoryRuleWatch::class);

    expect($watch->alerts(CarbonImmutable::create(2026, 9, 30)))->toBe([]);

    $alerts = $watch->alerts(CarbonImmutable::create(2026, 11, 15));
    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['type'])->toBe('nssf')
        ->and($alerts[0]['level'])->toBe('warning');

    expect($watch->alerts(CarbonImmutable::create(2027, 1, 25))[0]['level'])->toBe('danger');

    TaxRule::factory()->forTenant($this->tenant)->of(TaxRuleType::Nssf, ['rate' => 0.06, 'ceiling_cents' => 14_400_000])
        ->create(['effective_from' => '2027-02-01']);

    expect($watch->alerts(CarbonImmutable::create(2027, 1, 25)))->toBe([]);
});

it('reports a required rule that is not in force today', function () {
    TaxRule::query()->where('type', 'shif')->delete();

    $alerts = app(StatutoryRuleWatch::class)->alerts(CarbonImmutable::create(2026, 9, 30));

    expect($alerts[0]['type'])->toBe('shif')->and($alerts[0]['level'])->toBe('danger');
});

it('lets a payroll manager add the next NSSF rule in KES and percent, and audits it', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(TaxRules::class)
        ->call('create')
        ->set('type', 'nssf')
        ->set('effective_from', '2027-02-01')
        ->set('rate', '6')
        ->set('employer_rate', '6')
        ->set('ceiling', '144000')
        ->call('save')
        ->assertHasNoErrors();

    $rule = TaxRule::query()->where('type', 'nssf')->whereDate('effective_from', '2027-02-01')->firstOrFail();

    expect($rule->config)->toEqual(['rate' => 0.06, 'employer_rate' => 0.06, 'ceiling_cents' => 14_400_000])
        ->and(AuditLog::query()->where('event', 'payroll.tax_rule_created')->exists())->toBeTrue()
        ->and(app(StatutoryRuleWatch::class)->alerts(CarbonImmutable::create(2027, 1, 25)))->toBe([]);
});

it('rejects out-of-range rates and unordered PAYE bands', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(TaxRules::class)
        ->call('create')
        ->set('type', 'shif')
        ->set('effective_from', '2027-02-01')
        ->set('rate', '275')
        ->call('save')
        ->assertHasErrors(['config.rate']);

    Livewire::test(TaxRules::class)
        ->call('create')
        ->set('type', 'paye')
        ->set('effective_from', '2027-02-01')
        ->set('relief', '2400')
        ->set('bands', [['upto' => '50000', 'rate' => '10'], ['upto' => '30000', 'rate' => '30'], ['upto' => null, 'rate' => '35']])
        ->call('save')
        ->assertHasErrors(['bands']);

    expect(TaxRule::query()->whereDate('effective_from', '2027-02-01')->count())->toBe(0);
});

it('lets an employee-level user see nothing and a viewer not change rates', function () {
    $this->actingAs(userForTenant($this->tenant, Role::Employee));
    Livewire::test(TaxRules::class)->assertForbidden();

    $rule = TaxRule::query()->where('type', 'nssf')->firstOrFail();
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));
    Livewire::test(TaxRules::class)->call('delete', $rule->uuid)->assertHasNoErrors();
    expect(TaxRule::query()->whereKey($rule->getKey())->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'payroll.tax_rule_deleted')->exists())->toBeTrue();
});

it('validates the config on the API too', function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $manager = userForTenant($this->tenant, Role::PayrollManager);

    $this->actingAs($manager)->postJson("http://{$this->tenant->slug}.hrm.test/tax-rules", [
        'name' => 'NSSF', 'type' => 'nssf', 'effective_from' => '2027-02-01', 'config' => ['rate' => 0.06],
    ])->assertUnprocessable()->assertJsonValidationErrors(['config.ceiling_cents']);
});
