<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);

    $this->tenantA = tenantWithRoles(['slug' => 'tenant-a']);
    $this->tenantB = tenantWithRoles(['slug' => 'tenant-b']);

    $this->userA = userForTenant($this->tenantA, Role::HrManager);
    $this->userB = userForTenant($this->tenantB, Role::HrManager);

    $this->employeeA = Employee::factory()->forTenant($this->tenantA)->create();
    $this->employeeB = Employee::factory()->forTenant($this->tenantB)->create();
    Employee::factory()->count(4)->forTenant($this->tenantB)->create();
});

it('lets a user read an employee in their own tenant', function () {
    $this->actingAs($this->userA)
        ->getJson('http://hrm.test/employees/'.$this->employeeA->uuid)
        ->assertOk()
        ->assertJsonPath('data.employee_number', $this->employeeA->employee_number);
});

it('returns 404 for an employee belonging to another tenant', function () {
    $response = $this->actingAs($this->userA)
        ->getJson('http://hrm.test/employees/'.$this->employeeB->uuid);

    $response->assertNotFound();
    expect($response->getContent())->not->toContain($this->employeeB->employee_number);
});

it('never lists another tenant\'s employees on the index', function () {
    $response = $this->actingAs($this->userA)
        ->getJson('http://hrm.test/employees')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect(collect($response->json('data'))->pluck('uuid'))
        ->toContain($this->employeeA->uuid)
        ->not->toContain($this->employeeB->uuid);
});

it('blocks a user from entering another tenant via its subdomain', function () {
    $this->actingAs($this->userA)
        ->getJson('http://tenant-b.hrm.test/employees')
        ->assertForbidden();
});

it('scopes queries to the subdomain tenant, not the user\'s default', function () {
    $this->actingAs($this->userA)
        ->getJson('http://tenant-a.hrm.test/employees')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('404s an unknown subdomain', function () {
    $this->actingAs($this->userA)
        ->getJson('http://ghost.hrm.test/employees')
        ->assertNotFound();
});

it('denies a user without the employees.view permission', function () {
    $employee = userForTenant($this->tenantA, Role::Employee); // no employees.view

    $this->actingAs($employee)
        ->getJson('http://hrm.test/employees')
        ->assertForbidden();
});

it('denies a platform admin direct access to tenant HR data', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->getJson('http://tenant-a.hrm.test/employees/'.$this->employeeA->uuid)
        ->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson('http://hrm.test/employees')->assertUnauthorized();
});

it('enforces isolation at the policy layer independent of the query scope', function () {
    expect($this->userA->can('view', $this->employeeA))->toBeTrue()
        ->and($this->userA->can('view', $this->employeeB))->toBeFalse();
});
