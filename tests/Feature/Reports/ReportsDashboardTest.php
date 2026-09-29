<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Livewire\Reports\Index;
use App\Models\Employee;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'rptdash-co']);
    app(TenantContext::class)->set($this->tenant);

    Employee::factory()->forTenant($this->tenant)->count(2)->create(['status' => EmploymentStatus::Active]);
});

it('shows the overview charts above the report picker', function () {
    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $this->actingAs($owner);

    Livewire::test(Index::class)
        ->assertSee('Overview')
        ->assertSee('Active headcount by department')
        ->assertSee('Detailed reports');
});

it('shows neither charts nor the report picker to a user without reports.view', function () {
    $plain = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($plain);

    Livewire::test(Index::class)
        ->assertDontSee('Overview')
        ->assertSee('You do not have access to any reports.');
});
