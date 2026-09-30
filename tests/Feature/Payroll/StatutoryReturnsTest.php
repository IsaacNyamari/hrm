<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'ret-co']);
    $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'payroll' => ['salary_basis' => 'fixed']]])->save();
    app(TenantContext::class)->set($this->tenant);
    $this->manager = userForTenant($this->tenant, Role::PayrollManager);

    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'first_name' => 'Amina', 'last_name' => 'Otieno', 'salary_amount_cents' => 10_000_000,
    ]);

    $period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    $this->run = app(PayrollService::class)->run($period);
    $this->url = "http://ret-co.hrm.test/payroll-runs/{$this->run->uuid}/statutory-returns";
});

function completeStatutoryDetails(Employee $employee): void
{
    $employee->statutoryDetail()->updateOrCreate([], [
        'national_id' => '12345678', 'tax_pin' => 'a123456789z', 'nssf_number' => 'NSSF0001', 'shif_number' => 'SHA-998877',
    ]);
}

it('reports missing or malformed identifiers per return', function () {
    $this->employee->statutoryDetail()->create(['tax_pin' => 'BAD-PIN']);

    $issues = $this->actingAs($this->manager)->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.employees', 1)
        ->json('data.issues');

    expect(collect($issues['kra'])->pluck('field')->all())->toBe(['kra_pin'])
        ->and(collect($issues['nssf'])->pluck('field')->all())->toContain('nssf_number', 'national_id', 'kra_pin')
        ->and($issues['sha'])->toHaveCount(1);
});

it('blocks a download with invalid details unless forced', function () {
    $this->actingAs($this->manager)->get("{$this->url}/sha")->assertUnprocessable()->assertJsonStructure(['issues' => ['sha']]);
    $this->actingAs($this->manager)->get("{$this->url}/sha?force=1")->assertOk();
});

it('exports the KRA, NSSF and SHA files once details are complete', function () {
    completeStatutoryDetails($this->employee);

    $kra = $this->actingAs($this->manager)->get("{$this->url}/kra")->assertOk();
    expect($kra->headers->get('content-disposition'))->toContain('kra-2026-09.csv')
        ->and($kra->getContent())->toContain('PIN of Employee', 'A123456789Z', 'Amina');

    $nssf = $this->actingAs($this->manager)->get("{$this->url}/nssf")->assertOk();
    expect($nssf->getContent())->toContain('NSSF0001', '12345678', 'Otieno');

    $sha = $this->actingAs($this->manager)->get("{$this->url}/sha")->assertOk();
    expect($sha->headers->get('content-disposition'))->toContain('sha-2026-09.xlsx');

    $this->actingAs($this->manager)->get("{$this->url}/all")->assertOk();
});

it('hides returns from users without payroll access and rejects unknown types', function () {
    $employeeUser = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employeeUser)->getJson($this->url)->assertForbidden();
    $this->actingAs($this->manager)->get("{$this->url}/bogus")->assertNotFound();
});
