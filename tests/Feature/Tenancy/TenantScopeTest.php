<?php

declare(strict_types=1);

use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    Employee::factory()->count(3)->forTenant($this->tenantA)->create();
    Employee::factory()->count(5)->forTenant($this->tenantB)->create();
});

it('only returns the bound tenant\'s rows', function () {
    Tenancy::set($this->tenantA);
    expect(Employee::count())->toBe(3);

    Tenancy::set($this->tenantB);
    expect(Employee::count())->toBe(5);
});

it('returns every row when no tenant is bound', function () {
    expect(Employee::count())->toBe(8);
});

it('can be bypassed explicitly with withoutTenantScope()', function () {
    Tenancy::set($this->tenantA);

    expect(Employee::withoutTenantScope()->count())->toBe(8);
});

it('auto-fills tenant_id from the context on create', function () {
    Tenancy::set($this->tenantA);

    $employee = Employee::create([
        'employee_number' => 'AUTO-1',
        'first_name' => 'Auto',
        'last_name' => 'Fill',
    ]);

    expect($employee->tenant_id)->toBe($this->tenantA->id);
});

it('does not override an explicitly provided tenant_id', function () {
    Tenancy::set($this->tenantA);

    $employee = Employee::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id,
        'employee_number' => 'EXPLICIT-1',
        'first_name' => 'Explicit',
        'last_name' => 'Tenant',
    ]);

    expect($employee->tenant_id)->toBe($this->tenantB->id);
});

it('assigns an ordered uuid on create', function () {
    Tenancy::set($this->tenantA);

    $employee = Employee::create([
        'employee_number' => 'UUID-1',
        'first_name' => 'Has',
        'last_name' => 'Uuid',
    ]);

    expect($employee->uuid)->toBeString()->toHaveLength(36);
});

it('scopes relationship access through the tenant', function () {
    // tenantA relation always returns its own, regardless of bound context
    Tenancy::set($this->tenantB);

    expect($this->tenantA->employees()->count())->toBe(3);
});
