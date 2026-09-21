<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Finance\Assets;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Support\TenantContext;
use Livewire\Livewire;

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

it('creates an asset from the dashboard', function () {
    $this->actingAs($this->admin);

    Livewire::test(Assets::class)
        ->call('startCreate')
        ->set('form.asset_tag', 'AST-9001')
        ->set('form.name', 'Dell Latitude')
        ->set('form.category', 'laptop')
        ->call('save')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    expect(Asset::query()->where('asset_tag', 'AST-9001')->exists())->toBeTrue();
});

it('edits an asset from the dashboard', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create(['name' => 'Old name']);

    $this->actingAs($this->admin);

    Livewire::test(Assets::class)
        ->call('startEdit', $asset->uuid)
        ->assertSet('form.name', 'Old name')
        ->set('form.name', 'New name')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($asset->fresh()->name)->toBe('New name');
});

it('assigns and returns an asset from the dashboard', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin);

    $component = Livewire::test(Assets::class)
        ->call('startAssign', $asset->uuid)
        ->set('assignEmployeeId', (string) $this->employee->id)
        ->call('assign')
        ->assertDispatched('toast-show');

    expect($asset->fresh()->status->value)->toBe('assigned');

    $assignmentUuid = AssetAssignment::query()->where('asset_id', $asset->id)->where('status', 'active')->value('uuid');

    $component->call('returnAsset', $assignmentUuid)->assertDispatched('toast-show');

    expect($asset->fresh()->status->value)->toBe('available');
});

it('retires an asset from the dashboard', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin);

    Livewire::test(Assets::class)
        ->call('retireAsset', $asset->uuid)
        ->assertDispatched('toast-show');

    expect($asset->fresh()->status->value)->toBe('retired');
});

it('deletes an asset with no assignment history from the dashboard', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->admin);

    Livewire::test(Assets::class)->call('remove', $asset->uuid);

    expect(Asset::query()->find($asset->id))->toBeNull();
});

it('refuses to delete an asset that has assignment history from the dashboard', function () {
    $asset = Asset::factory()->forTenant($this->tenant)->create(['status' => 'available']);
    AssetAssignment::factory()->forAsset($asset)->create(['employee_id' => $this->employee->id, 'status' => 'returned']);

    $this->actingAs($this->admin);

    Livewire::test(Assets::class)
        ->call('remove', $asset->uuid)
        ->assertDispatched('toast-show');

    expect(Asset::query()->find($asset->id))->not->toBeNull();
});

it('forbids managing assets from the dashboard without the assets permission', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff);

    Livewire::test(Assets::class)
        ->call('startCreate')
        ->assertForbidden();
});

it('does not leak another tenant\'s asset', function () {
    $other = tenantWithRoles(['slug' => 'rival-asset-co']);
    $asset = Asset::factory()->forTenant($other)->create();

    $this->actingAs($this->admin)
        ->getJson("http://asset-co.hrm.test/assets/{$asset->uuid}")
        ->assertNotFound();
});
