<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'api-co', 'name' => 'API Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::HrAdministrator);
    Employee::factory()->forTenant($this->tenant)->count(3)->create();
});

function apiToken(array $abilities): string
{
    return test()->user->createToken('test', $abilities)->plainTextToken;
}

it('rejects an unauthenticated request', function () {
    $this->getJson('http://api-co.hrm.test/api/v1/employees')->assertUnauthorized();
});

it('rejects a token that lacks the ability', function () {
    $token = apiToken(['payroll:read']);

    $this->withToken($token)
        ->getJson('http://api-co.hrm.test/api/v1/employees')
        ->assertForbidden();
});

it('lists employees for a token with the ability, scoped to the tenant', function () {
    $other = tenantWithRoles(['slug' => 'rival-api-co']);
    Employee::factory()->forTenant($other)->count(5)->create();

    $token = apiToken(['employees:read']);

    $response = $this->withToken($token)
        ->getJson('http://api-co.hrm.test/api/v1/employees')
        ->assertOk()
        ->assertJsonStructure(['data' => [['uuid', 'employee_number', 'full_name', 'status']], 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(3);
});

it('returns the token identity and organization from /me', function () {
    $token = apiToken(['employees:read']);

    $this->withToken($token)
        ->getJson('http://api-co.hrm.test/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.organization.uuid', $this->tenant->uuid)
        ->assertJsonPath('data.token.abilities', ['employees:read']);
});

it('creates a leave request through the API with a write-scoped token', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    $type = LeaveType::query()->firstOrFail();

    $token = apiToken(['leave:write']);

    $this->withToken($token)
        ->postJson('http://api-co.hrm.test/api/v1/leave-requests', [
            'employee_uuid' => $employee->uuid,
            'leave_type_uuid' => $type->uuid,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Via API',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.employee_uuid', $employee->uuid);
});

it('will not accept a write with only a read token', function () {
    $token = apiToken(['leave:read']);

    $this->withToken($token)
        ->postJson('http://api-co.hrm.test/api/v1/leave-requests', [])
        ->assertForbidden();
});
