<?php

declare(strict_types=1);

use App\Facades\Tenancy;
use App\Support\TenantCache;

beforeEach(function () {
    $this->tenantA = tenantWithRoles(['slug' => 'tc-a']);
    $this->tenantB = tenantWithRoles(['slug' => 'tc-b']);
});

it('namespaces cached values per tenant', function () {
    $a = Tenancy::run($this->tenantA, fn () => TenantCache::remember('greeting', 60, fn () => 'hello A'));
    $b = Tenancy::run($this->tenantB, fn () => TenantCache::remember('greeting', 60, fn () => 'hello B'));

    expect($a)->toBe('hello A')->and($b)->toBe('hello B')
        ->and(Tenancy::run($this->tenantA, fn () => TenantCache::remember('greeting', 60, fn () => 'recomputed')))->toBe('hello A');
});

it('forgets only the current tenant\'s key', function () {
    Tenancy::run($this->tenantA, fn () => TenantCache::remember('k', 60, fn () => 'A1'));
    Tenancy::run($this->tenantB, fn () => TenantCache::remember('k', 60, fn () => 'B1'));

    Tenancy::run($this->tenantA, fn () => TenantCache::forget('k'));

    expect(Tenancy::run($this->tenantA, fn () => TenantCache::remember('k', 60, fn () => 'A2')))->toBe('A2')
        ->and(Tenancy::run($this->tenantB, fn () => TenantCache::remember('k', 60, fn () => 'B2')))->toBe('B1');
});

it('busts a versioned group for the current tenant', function () {
    $key = Tenancy::run($this->tenantA, fn () => TenantCache::versionedKey('org', 'tree'));
    Tenancy::run($this->tenantA, fn () => TenantCache::bumpVersion('org'));
    $key2 = Tenancy::run($this->tenantA, fn () => TenantCache::versionedKey('org', 'tree'));

    expect($key)->not->toBe($key2);
});
