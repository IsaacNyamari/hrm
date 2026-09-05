<?php

declare(strict_types=1);

use App\Actions\Employees\GenerateEmployeeNumber;
use App\Models\Employee;

it('generates a padded sequential number in the default format', function () {
    $tenant = tenantWithRoles();
    $generator = app(GenerateEmployeeNumber::class);

    expect($generator->forTenant($tenant))->toBe('EMP-000001');

    Employee::factory()->forTenant($tenant)->create(['employee_number' => 'EMP-000001']);

    expect($generator->forTenant($tenant))->toBe('EMP-000002');
});

it('honours a tenant-configured format', function () {
    $tenant = tenantWithRoles([
        'settings' => ['employee_number' => ['format' => '{PREFIX}-EMP-{SEQ}', 'padding' => 4]],
    ]);

    expect(app(GenerateEmployeeNumber::class)->forTenant($tenant))->toMatch('/^[A-Z]+-EMP-0001$/');
});

it('skips numbers already taken by a soft-deleted employee', function () {
    $tenant = tenantWithRoles();

    $employee = Employee::factory()->forTenant($tenant)->create(['employee_number' => 'EMP-000001']);
    $employee->delete();

    expect(app(GenerateEmployeeNumber::class)->forTenant($tenant))->toBe('EMP-000002');
});

it('numbers are isolated per tenant', function () {
    $a = tenantWithRoles();
    $b = tenantWithRoles();
    $generator = app(GenerateEmployeeNumber::class);

    Employee::factory()->forTenant($a)->count(3)->create();

    expect($generator->forTenant($a))->toBe('EMP-000004')
        ->and($generator->forTenant($b))->toBe('EMP-000001');
});
