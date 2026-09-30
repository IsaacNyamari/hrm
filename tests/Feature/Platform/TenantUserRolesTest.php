<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Platform\TenantDetail;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'platform-roles-co']);
    $this->employee = userForTenant($this->tenant, Role::Employee);
});

it('lets a super admin make a tenant user the Tenant Owner ("company head")', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(TenantDetail::class, ['tenant' => $this->tenant])
        ->call('updateUserRole', $this->employee->id, Role::TenantOwner->value);

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->employee->fresh()->hasRole(Role::TenantOwner->value))->toBeTrue();
    });

    expect(AuditLog::query()->where('event', 'platform.tenant.user_role_changed')->where('auditable_id', $this->employee->id)->exists())->toBeTrue();
});

it('refuses a platform role without users.manage from changing a tenant user\'s role', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create());

    Livewire::test(TenantDetail::class, ['tenant' => $this->tenant])
        ->call('updateUserRole', $this->employee->id, Role::TenantOwner->value)
        ->assertForbidden();

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->employee->fresh()->hasRole(Role::TenantOwner->value))->toBeFalse();
    });
});

it('refuses to assign a platform role to a tenant user', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(TenantDetail::class, ['tenant' => $this->tenant])
        ->call('updateUserRole', $this->employee->id, Role::PlatformAdmin->value)
        ->assertStatus(422);
});
