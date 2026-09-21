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

    // Assert against the filtered dataset itself rather than raw page text: the
    // "manager" picker in the add/edit form intentionally lists every employee
    // regardless of the table's search/status filter, so a plain assertSee
    // would false-fail on a name that only appears there.
    Livewire::test(Index::class)
        ->set('search', 'Findme')
        ->assertViewHas('employees', fn ($employees) => $employees->pluck('first_name')->all() === ['Findme'])
        ->set('search', '')
        ->set('status', EmploymentStatus::Terminated->value)
        ->assertViewHas('employees', fn ($employees) => $employees->pluck('first_name')->all() === ['Hidden']);
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

it('creates an employee from the dashboard', function () {
    $this->actingAs($this->hr);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)
        ->call('startCreate')
        ->set('form.first_name', 'Dashboard')
        ->set('form.last_name', 'Created')
        ->set('form.email', 'dashboard.created@dir-co.test')
        ->call('save')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    expect(Employee::query()->where('email', 'dashboard.created@dir-co.test')->exists())->toBeTrue();
});

it('edits an employee from the dashboard', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['job_title' => 'Junior']);

    $this->actingAs($this->hr);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)
        ->call('startEdit', $employee->uuid)
        ->assertSet('form.first_name', $employee->first_name)
        ->set('form.job_title', 'Senior')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($employee->fresh()->job_title)->toBe('Senior');
});

it('forbids creating an employee from the dashboard without employees.create', function () {
    $viewer = userForTenant($this->tenant, Role::Supervisor); // has employees.view, not create
    $this->actingAs($viewer);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)
        ->call('startCreate')
        ->assertForbidden();
});

it('deletes an employee from the dashboard when the user has employees.delete', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    $admin = userForTenant($this->tenant, Role::HrAdministrator);

    $this->actingAs($admin);
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)->call('remove', $employee->uuid);

    expect(Employee::withTrashed()->find($employee->id)->trashed())->toBeTrue();
});

it('forbids deleting an employee from the dashboard without employees.delete', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hr); // HR Manager lacks employees.delete
    app(TenantContext::class)->set($this->tenant->fresh());

    Livewire::test(Index::class)
        ->call('remove', $employee->uuid)
        ->assertForbidden();
});
