<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\EmployeeCreated;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'crud-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('creates an employee with an auto-generated number and nested records', function () {
    Event::fake([EmployeeCreated::class]);
    $department = Department::factory()->forTenant($this->tenant)->create();

    $response = $this->actingAs($this->hr)->postJson('http://crud-co.hrm.test/employees', [
        'first_name' => 'Amina',
        'last_name' => 'Otieno',
        'email' => 'amina@crud-co.test',
        'department_id' => $department->id,
        'hire_date' => '2026-01-06',
        'emergency_contacts' => [
            ['name' => 'John Otieno', 'phone' => '254700000001', 'relationship' => 'Spouse', 'is_primary' => true],
        ],
        'bank_accounts' => [
            ['bank_name' => 'Equity', 'account_name' => 'Amina Otieno', 'account_number' => '0123456789', 'is_primary' => true],
        ],
        'statutory' => ['tax_pin' => 'A012345678Z'],
    ]);

    $response->assertCreated()->assertJsonPath('data.employee_number', 'EMP-000001');

    $employee = Employee::query()->where('tenant_id', $this->tenant->id)->first();
    expect($employee->emergencyContacts)->toHaveCount(1)
        ->and($employee->bankAccounts)->toHaveCount(1)
        ->and($employee->statutoryDetail->tax_pin)->toBe('A012345678Z');

    // account number is encrypted at rest
    $raw = DB::table('employee_bank_accounts')->value('account_number');
    expect($raw)->not->toBe('0123456789');

    Event::assertDispatched(EmployeeCreated::class);
});

it('masks the bank account number in the API', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    $employee->bankAccounts()->create([
        'tenant_id' => $this->tenant->id,
        'bank_name' => 'KCB', 'account_name' => 'X', 'account_number' => '9988776655',
    ]);

    $this->actingAs($this->hr)
        ->getJson("http://crud-co.hrm.test/employees/{$employee->uuid}")
        ->assertOk()
        ->assertJsonPath('data.bank_accounts.0.account_number', '••••••6655');
});

it('forbids creating an employee without employees.create', function () {
    $viewer = userForTenant($this->tenant, Role::Supervisor); // has employees.view, not create

    $this->actingAs($viewer)
        ->postJson('http://crud-co.hrm.test/employees', ['first_name' => 'No', 'last_name' => 'Access'])
        ->assertForbidden();
});

it('updates (HR manager) and soft-deletes (HR admin) an employee', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['job_title' => 'Junior']);

    $this->actingAs($this->hr)
        ->putJson("http://crud-co.hrm.test/employees/{$employee->uuid}", ['job_title' => 'Senior'])
        ->assertOk()
        ->assertJsonPath('data.job_title', 'Senior');

    // delete requires employees.delete — HR Manager does not have it
    $this->actingAs($this->hr)
        ->deleteJson("http://crud-co.hrm.test/employees/{$employee->uuid}")
        ->assertForbidden();

    $admin = userForTenant($this->tenant, Role::HrAdministrator);
    $this->actingAs($admin)
        ->deleteJson("http://crud-co.hrm.test/employees/{$employee->uuid}")
        ->assertNoContent();

    expect(Employee::withTrashed()->find($employee->id)->trashed())->toBeTrue();
});

it('cannot update an employee in another tenant', function () {
    $other = tenantWithRoles(['slug' => 'other-co']);
    $victim = Employee::factory()->forTenant($other)->create();

    $this->actingAs($this->hr)
        ->putJson("http://crud-co.hrm.test/employees/{$victim->uuid}", ['job_title' => 'Hacked'])
        ->assertNotFound();
});

it('rejects a duplicate work email within the tenant', function () {
    Employee::factory()->forTenant($this->tenant)->create(['email' => 'taken@crud-co.test']);

    $this->actingAs($this->hr)
        ->postJson('http://crud-co.hrm.test/employees', [
            'first_name' => 'Dup', 'last_name' => 'Email', 'email' => 'taken@crud-co.test',
        ])
        ->assertStatus(422);
});

it('shows pay only to people who hold the salary permission', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 4_500_000, 'salary_currency' => 'KES']);
    $supervisor = userForTenant($this->tenant, Role::Supervisor); // can view employees, not pay
    $payroll = userForTenant($this->tenant, Role::PayrollManager);

    $this->actingAs($supervisor)
        ->getJson("http://crud-co.hrm.test/employees/{$employee->uuid}")
        ->assertOk()
        ->assertJsonMissingPath('data.salary_amount_cents');

    $this->actingAs($payroll)
        ->getJson("http://crud-co.hrm.test/employees/{$employee->uuid}")
        ->assertOk()
        ->assertJsonPath('data.salary_amount_cents', 4_500_000);
});
