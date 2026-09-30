<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    $this->user = userForTenant($this->tenant, Role::PayrollManager);
});

it('records each login and failed login exactly once', function () {
    event(new Login('web', $this->user, false));
    event(new Failed('web', null, ['email' => 'nobody@example.com', 'password' => 'secret']));

    expect(AuditLog::query()->where('event', 'auth.login')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'auth.failed')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'auth.failed')->first()->properties)->not->toHaveKey('password');
});
