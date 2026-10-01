<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;
use App\Support\Auditor;
use App\Support\AuditSummary;
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

it('records the same event for the same item only once per request', function () {
    $auditor = app(Auditor::class);
    $tenant = $this->tenant;

    $auditor->log('demo.happened', $tenant, ['a' => 1]);
    $auditor->log('demo.happened', $tenant, ['a' => 1]);
    $auditor->log('demo.happened', $tenant, ['a' => 2]);

    expect(AuditLog::query()->where('event', 'demo.happened')->count())->toBe(2);
});

it('describes an audit entry in plain language instead of raw JSON', function () {
    $summary = app(AuditSummary::class);

    $status = AuditLog::factory()->create(['event' => 'platform.tenant.activated', 'properties' => ['tenant_id' => $this->tenant->id, 'from' => 'suspended', 'to' => 'active']]);
    $update = AuditLog::factory()->create(['event' => 'employee.updated', 'properties' => ['changes' => ['job_title' => 'Head', 'salary_cents' => 500000], 'original' => ['job_title' => 'Clerk', 'salary_cents' => 400000]]]);

    expect($summary->lines($status))->toBe([sprintf('"%s" was changed from suspended to active.', $this->tenant->name)])
        ->and($summary->lines($update))->toBe(['Job Title changed from Clerk to Head.', 'Salary changed from 4,000.00 to 5,000.00.']);
});

it('gives audit events a readable title', function () {
    $summary = app(AuditSummary::class);

    expect($summary->title('platform.tenant.activated'))->toBe('Organization activated')
        ->and($summary->title('employee.updated'))->toBe('Employee updated')
        ->and($summary->title('platform.widget.sync_failed'))->toBe('Widget sync failed');
});
