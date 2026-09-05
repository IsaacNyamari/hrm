<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Livewire\Employees\Index;
use App\Models\Employee;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'dir-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('lists only the current tenant\'s employees', function () {
    $mine = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Wangari', 'last_name' => 'Mine']);

    $other = tenantWithRoles(['slug' => 'rival-co']);
    Employee::factory()->forTenant($other)->create(['first_name' => 'Rival', 'last_name' => 'Person']);

    $this->actingAs($this->hr);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)
        ->assertSee('Wangari')
        ->assertDontSee('Rival');
});

it('filters by search term and status', function () {
    $this->actingAs($this->hr);
    app(TenantContext::class)->set($this->tenant->fresh());

    Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Findme', 'last_name' => 'Alpha', 'status' => EmploymentStatus::Active]);
    Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Hidden', 'last_name' => 'Beta', 'status' => EmploymentStatus::Terminated]);

    Livewire::test(Index::class)
        ->set('search', 'Findme')
        ->assertSee('Findme')
        ->assertDontSee('Hidden')
        ->set('search', '')
        ->set('status', EmploymentStatus::Terminated->value)
        ->assertSee('Hidden')
        ->assertDontSee('Findme');
});

it('paginates', function () {
    $this->actingAs($this->hr);
    app(TenantContext::class)->set($this->tenant->fresh());

    Employee::factory()->forTenant($this->tenant)->count(20)->create();

    Livewire::test(Index::class)
        ->assertViewHas('employees', fn ($employees) => $employees->count() === 15 && $employees->total() === 20);
});

it('is blocked without the employees.view permission', function () {
    $noAccess = userForTenant($this->tenant, Role::FinanceManager); // no employees.view
    $this->actingAs($noAccess);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)->assertForbidden();
});
