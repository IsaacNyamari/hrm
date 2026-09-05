<?php

declare(strict_types=1);

use App\Facades\Tenancy;
use App\Models\Tenant;
use App\Support\TenantContext;

it('starts empty', function () {
    expect(Tenancy::has())->toBeFalse()
        ->and(Tenancy::get())->toBeNull()
        ->and(Tenancy::id())->toBeNull();
});

it('binds and reads a tenant', function () {
    $tenant = Tenant::factory()->create();

    Tenancy::set($tenant);

    expect(Tenancy::has())->toBeTrue()
        ->and(Tenancy::id())->toBe($tenant->id)
        ->and(Tenancy::getOrFail()->is($tenant))->toBeTrue();
});

it('throws from getOrFail when unbound', function () {
    Tenancy::getOrFail();
})->throws(RuntimeException::class);

it('clears the tenant', function () {
    Tenancy::set(Tenant::factory()->create());
    Tenancy::forget();

    expect(Tenancy::has())->toBeFalse();
});

it('runs a callback with a tenant bound and restores the previous context', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    Tenancy::set($a);

    $seen = Tenancy::run($b, fn () => Tenancy::id());

    expect($seen)->toBe($b->id)
        ->and(Tenancy::id())->toBe($a->id);
});

it('runs a callback with no tenant and restores afterwards', function () {
    $a = Tenant::factory()->create();
    Tenancy::set($a);

    $seen = Tenancy::runWithout(fn () => Tenancy::has());

    expect($seen)->toBeFalse()
        ->and(Tenancy::id())->toBe($a->id);
});

it('is the same singleton instance behind the facade', function () {
    expect(app(TenantContext::class))->toBe(app(TenantContext::class));
});
