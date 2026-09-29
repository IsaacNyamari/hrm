<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Organization\Chart;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Organization\OrgChartService;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'chart-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('builds the reporting tree without N+1', function () {
    app(TenantContext::class)->set($this->tenant);

    $ceo = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Cee', 'last_name' => 'Oh']);
    $lead = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Team', 'last_name' => 'Lead', 'manager_id' => $ceo->id]);
    Employee::factory()->forTenant($this->tenant)->count(3)->create(['manager_id' => $lead->id]);

    $tree = app(OrgChartService::class)->reportingTree();

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['name'])->toBe('Cee Oh')
        ->and($tree[0]['reports'])->toHaveCount(1)
        ->and($tree[0]['reports'][0]['name'])->toBe('Team Lead')
        ->and($tree[0]['reports'][0]['reports'])->toHaveCount(3);
});

it('builds the department tree', function () {
    app(TenantContext::class)->set($this->tenant);

    $root = Department::factory()->forTenant($this->tenant)->create(['name' => 'Company']);
    $child = Department::factory()->forTenant($this->tenant)->create(['name' => 'Engineering', 'parent_id' => $root->id]);
    Department::factory()->forTenant($this->tenant)->create(['name' => 'Backend', 'parent_id' => $child->id]);

    $tree = app(OrgChartService::class)->departmentTree();

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['name'])->toBe('Company')
        ->and($tree[0]['children'][0]['name'])->toBe('Engineering')
        ->and($tree[0]['children'][0]['children'][0]['name'])->toBe('Backend');
});

it('does not include another tenant in the tree', function () {
    $other = tenantWithRoles(['slug' => 'rival-chart-co']);
    Employee::factory()->forTenant($other)->create(['first_name' => 'Rival', 'last_name' => 'Boss']);

    app(TenantContext::class)->set($this->tenant);
    Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Our', 'last_name' => 'Boss']);

    $tree = app(OrgChartService::class)->reportingTree();

    expect(collect($tree)->pluck('name'))->toContain('Our Boss')->not->toContain('Rival Boss');
});

it('renders the org chart Livewire page', function () {
    app(TenantContext::class)->set($this->tenant);
    Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Chart', 'last_name' => 'Root']);

    $this->actingAs($this->hr);

    Livewire::test(Chart::class)
        ->assertSee('Chart Root')
        ->set('view', 'departments')
        ->assertOk();
});

it('serves the reporting tree as JSON over HTTP', function () {
    $this->actingAs($this->hr)
        ->getJson('http://chart-co.hrm.test/org-chart')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('counts whole teams and department headcount', function () {
    app(TenantContext::class)->set($this->tenant);

    $engineering = Department::factory()->forTenant($this->tenant)->create(['name' => 'Engineering']);
    Department::factory()->forTenant($this->tenant)->create(['name' => 'Backend', 'parent_id' => $engineering->id]);
    $ceo = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Cee', 'last_name' => 'Oh', 'department_id' => $engineering->id]);
    $lead = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $ceo->id, 'department_id' => $engineering->id]);
    Employee::factory()->forTenant($this->tenant)->count(2)->create(['manager_id' => $lead->id]);

    $people = app(OrgChartService::class)->reportingTree();
    $departments = app(OrgChartService::class)->departmentTree();

    expect($people[0]['team_size'])->toBe(3)
        ->and($people[0]['initials'])->toBe('CO')
        ->and($departments[0]['headcount'])->toBe(2);
});

it('lets an authorized user reassign a manager from the organogram', function () {
    app(TenantContext::class)->set($this->tenant);
    $ceo = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Chart', 'last_name' => 'Root']);
    $otherLead = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Other', 'last_name' => 'Lead']);
    $report = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $ceo->id]);

    $this->actingAs($this->hr);

    Livewire::test(Chart::class)
        ->call('startReassign', $report->uuid)
        ->assertSet('newManagerId', (string) $ceo->id)
        ->set('newManagerId', (string) $otherLead->id)
        ->call('saveManager')
        ->assertDispatched('toast-show');

    expect($report->fresh()->manager_id)->toBe($otherLead->id);
});

it('lets a manager be cleared to top level from the organogram', function () {
    app(TenantContext::class)->set($this->tenant);
    $ceo = Employee::factory()->forTenant($this->tenant)->create();
    $report = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $ceo->id]);

    $this->actingAs($this->hr);

    Livewire::test(Chart::class)
        ->call('startReassign', $report->uuid)
        ->set('newManagerId', '')
        ->call('saveManager');

    expect($report->fresh()->manager_id)->toBeNull();
});

it('refuses to let an employee manage themselves', function () {
    app(TenantContext::class)->set($this->tenant);
    $employee = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hr);

    Livewire::test(Chart::class)
        ->call('startReassign', $employee->uuid)
        ->set('newManagerId', (string) $employee->id)
        ->call('saveManager')
        ->assertDispatched('toast-show');

    expect($employee->fresh()->manager_id)->toBeNull();
});

it('refuses a reassignment that would create a reporting loop', function () {
    app(TenantContext::class)->set($this->tenant);
    $ceo = Employee::factory()->forTenant($this->tenant)->create();
    $lead = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $ceo->id]);
    $report = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $lead->id]);

    $this->actingAs($this->hr);

    // Making the CEO report to their own report's report would loop.
    Livewire::test(Chart::class)
        ->call('startReassign', $ceo->uuid)
        ->set('newManagerId', (string) $report->id)
        ->call('saveManager')
        ->assertDispatched('toast-show');

    expect($ceo->fresh()->manager_id)->toBeNull();
});

it('hides manager reassignment from a user without employees.update', function () {
    app(TenantContext::class)->set($this->tenant);
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    // Supervisor holds employees.view (can see the chart) but not employees.update.
    $plain = userForTenant($this->tenant, Role::Supervisor);

    $this->actingAs($plain);

    Livewire::test(Chart::class)
        ->assertDontSeeHtml('wire:click="startReassign');

    Livewire::test(Chart::class)->call('startReassign', $employee->uuid)->assertForbidden();
});

it('draws the organogram with reporting counts and summary stats', function () {
    app(TenantContext::class)->set($this->tenant);
    $ceo = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Chart', 'last_name' => 'Root']);
    Employee::factory()->forTenant($this->tenant)->count(2)->create(['manager_id' => $ceo->id]);

    $this->actingAs($this->hr);

    Livewire::test(Chart::class)
        ->assertSeeHtml('data-test="organogram"')
        ->assertSee('Chart Root')
        ->assertSee('2 direct reports')
        ->assertSee('Managers');
});
