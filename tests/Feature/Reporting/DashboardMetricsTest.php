<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Services\Reporting\DashboardMetrics;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'metrics-co']);
    app(TenantContext::class)->set($this->tenant);
});

it('points the owner at the working hr-requests page, not the json endpoint', function () {
    $owner = userForTenant($this->tenant, Role::TenantOwner);
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    HrRequest::factory()->forEmployee($employee)->create(['status' => 'open']);

    $cards = app(DashboardMetrics::class)->for($owner);
    $hrCard = collect($cards)->firstWhere('key', 'hr_requests_open');

    expect($hrCard)->not->toBeNull()
        ->and($hrCard['value'])->toBe(1)
        ->and($hrCard['href'])->toBe('/hr-requests-board');
});

it('hides the hr requests card from a user without employees.view', function () {
    $employeeUser = userForTenant($this->tenant, Role::Employee);

    $cards = app(DashboardMetrics::class)->for($employeeUser);

    expect(collect($cards)->firstWhere('key', 'hr_requests_open'))->toBeNull();
});
