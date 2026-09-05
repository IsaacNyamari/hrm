<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'asset-co', 'name' => 'Asset Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->admin = userForTenant($this->tenant, Role::HrAdministrator); // assets.manage
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Dan']);
});

it('creates an asset in the register', function () {
    $this->actingAs($this->admin)
        ->postJson('http://asset-co.hrm.test/assets', [
            'asset_tag' => 'AST-0001', 'name' => 'MacBook Pro', 'category' => 'laptop',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'available');
});

it('assigns an available asset and flips its status', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->postJson("http://asset-co.hrm.test/assets/{$asset->uuid}/assignments", [
            'employee_id' => $this->employee->id, 'condition_out' => 'Good',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    expect($asset->fresh()->status->value)->toBe('assigned')
        ->and(AssetAssignment::query()->where('asset_id', $asset->id)->where('status', 'active')->count())->toBe(1);
});

it('refuses to assign an asset that already has an active holder', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create();
    AssetAssignment::factory()->forAsset($asset)->create(['employee_id' => $this->employee->id]);
    $asset->update(['status' => 'assigned']);

    $other = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin)
        ->postJson("http://asset-co.hrm.test/assets/{$asset->uuid}/assignments", ['employee_id' => $other->id])
        ->assertStatus(422);
});

it('checks an asset back in and frees it', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create(['status' => 'assigned']);
    $assignment = AssetAssignment::factory()->forAsset($asset)->create(['employee_id' => $this->employee->id]);

    $this->actingAs($this->admin)
        ->postJson("http://asset-co.hrm.test/asset-assignments/{$assignment->uuid}/return", ['condition_in' => 'Scratched'])
        ->assertOk()
        ->assertJsonPath('data.status', 'returned');

    expect($asset->fresh()->status->value)->toBe('available');
});

it('will not retire an asset that is still assigned', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create(['status' => 'assigned']);
    AssetAssignment::factory()->forAsset($asset)->create(['employee_id' => $this->employee->id]);

    $this->actingAs($this->admin)
        ->postJson("http://asset-co.hrm.test/assets/{$asset->uuid}/retire")
        ->assertStatus(422);
});

it('forbids asset management without the assets permission', function () {
    $staff = userForTenant($this->tenant, Role::Employee);
    $asset = Asset::factory()->forTenant($this->tenant)->create();

    $this->actingAs($staff)
        ->postJson("http://asset-co.hrm.test/assets/{$asset->uuid}/assignments", ['employee_id' => $this->employee->id])
        ->assertForbidden();
});

it('does not leak another tenant\'s asset', function () {
    $other = tenantWithRoles(['slug' => 'rival-asset-co']);
    $asset = Asset::factory()->forTenant($other)->create();

    $this->actingAs($this->admin)
        ->getJson("http://asset-co.hrm.test/assets/{$asset->uuid}")
        ->assertNotFound();
});
