<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'org-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
    $this->viewer = userForTenant($this->tenant, Role::Supervisor); // employees.view, no *.manage
});

it('creates, lists and updates a branch', function () {
    $create = $this->actingAs($this->hr)
        ->postJson('http://org-co.hrm.test/branches', ['name' => 'Nairobi HQ', 'code' => 'NBO', 'is_headquarters' => true])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Nairobi HQ');

    $this->actingAs($this->hr)->getJson('http://org-co.hrm.test/branches')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $uuid = $create->json('data.uuid');
    $this->actingAs($this->hr)
        ->putJson("http://org-co.hrm.test/branches/{$uuid}", ['name' => 'Nairobi Head Office'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nairobi Head Office');
});

it('forbids managing structure without the manage permission', function () {
    $this->actingAs($this->viewer)
        ->postJson('http://org-co.hrm.test/branches', ['name' => 'Sneaky'])
        ->assertForbidden();

    // but viewing is allowed
    $this->actingAs($this->viewer)->getJson('http://org-co.hrm.test/branches')->assertOk();
});

it('nests departments and rejects a circular parent', function () {
    $parent = Department::factory()->forTenant($this->tenant)->create(['name' => 'Operations']);
    $childResp = $this->actingAs($this->hr)->postJson('http://org-co.hrm.test/departments', [
        'name' => 'Logistics', 'parent_id' => $parent->id,
    ])->assertCreated();

    $childUuid = $childResp->json('data.uuid');
    $child = Department::query()->where('uuid', $childUuid)->first();

    // try to make the parent a child of its own child
    $this->actingAs($this->hr)
        ->putJson("http://org-co.hrm.test/departments/{$parent->uuid}", ['parent_id' => $child->id])
        ->assertStatus(422);
});

it('will not delete a department that still has employees', function () {
    $department = Department::factory()->forTenant($this->tenant)->create();
    Employee::factory()->forTenant($this->tenant)->create(['department_id' => $department->id]);

    $this->actingAs($this->hr)
        ->deleteJson("http://org-co.hrm.test/departments/{$department->uuid}")
        ->assertStatus(409);
});

it('enforces code uniqueness per tenant only', function () {
    Department::factory()->forTenant($this->tenant)->create(['code' => 'FIN']);

    $this->actingAs($this->hr)
        ->postJson('http://org-co.hrm.test/departments', ['name' => 'Finance 2', 'code' => 'FIN'])
        ->assertStatus(422);

    // same code is fine in a different tenant
    $other = tenantWithRoles(['slug' => 'rival-org-co']);
    $otherHr = userForTenant($other, Role::HrManager);
    $this->actingAs($otherHr)
        ->postJson('http://rival-org-co.hrm.test/departments', ['name' => 'Finance', 'code' => 'FIN'])
        ->assertCreated();
});

it('cannot touch another tenant\'s designation', function () {
    $other = tenantWithRoles(['slug' => 'other-org-co']);
    $designation = Designation::factory()->forTenant($other)->create();

    $this->actingAs($this->hr)
        ->putJson("http://org-co.hrm.test/designations/{$designation->uuid}", ['name' => 'Hijacked'])
        ->assertNotFound();
});
