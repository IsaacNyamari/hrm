<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeImport;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\FeatureGate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Storage::fake('local');

    $this->tenant = tenantWithRoles(['slug' => 'import-co']);
    $plan = Plan::factory()->unlimited()->create();
    Subscription::factory()->forTenant($this->tenant)->onPlan($plan)->create();
    app(FeatureGate::class)->flush($this->tenant);

    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
});

function csvUpload(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('employees.csv', $body);
}

it('previews a file, reporting valid and invalid rows without writing', function () {
    Department::factory()->forTenant($this->tenant)->create(['name' => 'Finance']);

    $csv = <<<'CSV'
    first_name,last_name,email,department,hire_date
    Grace,Wanjiru,grace@import-co.test,Finance,2026-02-01
    ,NoFirstName,bad@import-co.test,Finance,2026-02-01
    Peter,Kamau,not-an-email,Finance,2026-02-01
    CSV;

    $response = $this->actingAs($this->hr)
        ->postJson('http://import-co.hrm.test/employees/import/preview', ['file' => csvUpload($csv)])
        ->assertOk();

    $response->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.valid', 1)
        ->assertJsonPath('data.invalid', 2);

    expect(Employee::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('imports valid rows when confirmed and records a report', function () {
    Department::factory()->forTenant($this->tenant)->create(['name' => 'Engineering']);

    $csv = <<<'CSV'
    first_name,last_name,email,department,hire_date
    Ada,Lovelace,ada@import-co.test,Engineering,2026-03-01
    Alan,Turing,alan@import-co.test,Engineering,2026-03-02
    ,Bad,broken@import-co.test,Engineering,2026-03-03
    CSV;

    $preview = $this->actingAs($this->hr)
        ->postJson('http://import-co.hrm.test/employees/import/preview', ['file' => csvUpload($csv)])
        ->assertOk();

    $importUuid = $preview->json('data.import');

    $this->actingAs($this->hr)
        ->postJson("http://import-co.hrm.test/employees/import/{$importUuid}/confirm")
        ->assertStatus(202);

    $import = EmployeeImport::query()->where('uuid', $importUuid)->first();
    expect($import->status)->toBe(EmployeeImport::STATUS_COMPLETED)
        ->and($import->imported_rows)->toBe(2)
        ->and($import->failed_rows)->toBe(1);

    expect(Employee::query()->where('tenant_id', $this->tenant->id)->pluck('email')->all())
        ->toContain('ada@import-co.test', 'alan@import-co.test');
});

it('cannot confirm the same import twice', function () {
    $csv = "first_name,last_name,email\nSam,Test,sam@import-co.test\n";
    $importUuid = $this->actingAs($this->hr)
        ->postJson('http://import-co.hrm.test/employees/import/preview', ['file' => csvUpload($csv)])
        ->json('data.import');

    $this->actingAs($this->hr)->postJson("http://import-co.hrm.test/employees/import/{$importUuid}/confirm")->assertStatus(202);
    $this->actingAs($this->hr)->postJson("http://import-co.hrm.test/employees/import/{$importUuid}/confirm")->assertStatus(409);
});

it('exports the tenant\'s employees as a spreadsheet', function () {
    Employee::factory()->forTenant($this->tenant)->count(3)->create();
    $otherTenant = tenantWithRoles(['slug' => 'other-export-co']);
    Employee::factory()->forTenant($otherTenant)->count(5)->create();

    $response = $this->actingAs($this->hr)->get('http://import-co.hrm.test/employees/export?format=csv');

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('employees.csv');
});

it('forbids import for a user without employees.create', function () {
    $viewer = userForTenant($this->tenant, Role::Supervisor);

    $this->actingAs($viewer)
        ->postJson('http://import-co.hrm.test/employees/import/preview', ['file' => csvUpload("first_name,last_name\nA,B\n")])
        ->assertForbidden();
});
