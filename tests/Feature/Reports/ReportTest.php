<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Support\TenantContext;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'rpt-co', 'name' => 'Rpt Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    Employee::factory()->forTenant($this->tenant)->count(3)->create(['status' => EmploymentStatus::Active]);
    Employee::factory()->forTenant($this->tenant)->create(['status' => EmploymentStatus::Terminated]);
});

it('lists only the reports the caller may run', function () {
    $catalogue = $this->actingAs($this->hr)
        ->getJson('http://rpt-co.hrm.test/reports/catalogue')
        ->assertOk()
        ->json('data');

    $keys = collect($catalogue)->pluck('key');
    expect($keys)->toContain('headcount', 'payroll-summary', 'expense-claims');

    $plain = userForTenant($this->tenant, Role::Employee); // no reports.view
    $this->actingAs($plain)
        ->getJson('http://rpt-co.hrm.test/reports/catalogue')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('runs the headcount report scoped to the tenant', function () {
    // noise in another tenant that must not be counted
    $other = tenantWithRoles(['slug' => 'rival-rpt-co']);
    Employee::factory()->forTenant($other)->count(5)->create();

    $response = $this->actingAs($this->hr)
        ->getJson('http://rpt-co.hrm.test/reports/headcount')
        ->assertOk();

    expect($response->json('data.summary.Total employees'))->toBe(4)
        ->and($response->json('data.columns'))->toBe(['Department', 'Branch', 'Status', 'Headcount']);

    $total = collect($response->json('data.rows'))->sum(fn (array $row) => $row[3]);
    expect($total)->toBe(4);
});

it('blocks a report whose domain permission the caller lacks', function () {
    $deptManager = userForTenant($this->tenant, Role::DepartmentManager); // reports.view but no payroll.view

    $this->actingAs($deptManager)
        ->getJson('http://rpt-co.hrm.test/reports/payroll-summary')
        ->assertForbidden();
});

it('404s an unknown report key', function () {
    $this->actingAs($this->hr)
        ->getJson('http://rpt-co.hrm.test/reports/does-not-exist')
        ->assertNotFound();
});

it('exports a report as a spreadsheet', function () {
    Excel::fake();

    $this->actingAs($this->hr)
        ->get('http://rpt-co.hrm.test/reports/headcount/export?format=xlsx')
        ->assertOk();

    Excel::assertDownloaded('headcount.xlsx');
});

it('requires reports.export for the download endpoint', function () {
    $deptManager = userForTenant($this->tenant, Role::DepartmentManager); // reports.view, not reports.export

    $this->actingAs($deptManager)
        ->get('http://rpt-co.hrm.test/reports/headcount/export')
        ->assertForbidden();
});

it('renders a print-ready HTML report', function () {
    $this->actingAs($this->hr)
        ->get('http://rpt-co.hrm.test/reports/headcount/print')
        ->assertOk()
        ->assertSee('Headcount by department')
        ->assertSee('<table', false);
});

it('returns permission-filtered dashboard metrics', function () {
    $response = $this->actingAs($this->hr)
        ->getJson('http://rpt-co.hrm.test/dashboard/metrics')
        ->assertOk();

    $keys = collect($response->json('data'))->pluck('key');
    expect($keys)->toContain('headcount', 'vacancies_open', 'expenses_pending');
});
