<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'imp-co', 'name' => 'Imp Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->operator = User::factory()->create(['is_platform_admin' => true, 'tenant_id' => null]);
    $this->target = userForTenant($this->tenant, Role::Employee);
});

it('lets a platform operator impersonate a tenant user and records it', function () {
    $this->actingAs($this->operator)
        ->post("http://imp-co.hrm.test/impersonate/{$this->target->id}")
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($this->target->id)
        ->and(session('impersonator_id'))->toBe($this->operator->id)
        ->and(AuditLog::query()->where('event', 'impersonation.started')->exists())->toBeTrue();
});

it('restores the operator when impersonation stops', function () {
    $this->actingAs($this->operator)->post("http://imp-co.hrm.test/impersonate/{$this->target->id}");

    $this->post('http://imp-co.hrm.test/impersonate/stop')->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($this->operator->id)
        ->and(session()->has('impersonator_id'))->toBeFalse()
        ->and(AuditLog::query()->where('event', 'impersonation.stopped')->exists())->toBeTrue();
});

it('refuses to impersonate another platform operator', function () {
    $otherOperator = User::factory()->create(['is_platform_admin' => true, 'tenant_id' => null]);

    $this->actingAs($this->operator)
        ->post("http://imp-co.hrm.test/impersonate/{$otherOperator->id}")
        ->assertStatus(422);
});

it('forbids impersonation for a non-platform user', function () {
    $this->actingAs($this->target)
        ->post("http://imp-co.hrm.test/impersonate/{$this->operator->id}")
        ->assertForbidden();
});
