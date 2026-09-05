<?php

declare(strict_types=1);

use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\NotificationPreference;
use App\Notifications\LeaveDecisionNotification;
use App\Support\NotificationChannels;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'pref-co', 'name' => 'Pref Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
});

it('shows every configurable category defaulting to email on', function () {
    $data = $this->actingAs($this->user)
        ->getJson('http://pref-co.hrm.test/me/notification-preferences')
        ->assertOk()
        ->json('data');

    expect($data)->not->toBeEmpty()
        ->and(collect($data)->every(fn (array $row): bool => $row['mail'] === true && $row['database'] === true))->toBeTrue()
        ->and(collect($data)->pluck('category'))->toContain('leave', 'payroll', 'expenses');
});

it('lets the user silence email for a category', function () {
    $this->actingAs($this->user)
        ->putJson('http://pref-co.hrm.test/me/notification-preferences', ['category' => 'leave', 'mail' => false])
        ->assertOk();

    $row = collect($this->actingAs($this->user)->getJson('http://pref-co.hrm.test/me/notification-preferences')->json('data'))
        ->firstWhere('category', 'leave');

    expect($row['mail'])->toBeFalse()
        ->and(NotificationChannels::forUser($this->user, NotificationCategory::Leave))->toBe(['database']);
});

it('keeps database delivery even when email is off', function () {
    NotificationPreference::query()->create([
        'user_id' => $this->user->id, 'category' => 'leave', 'channels' => ['mail' => false],
    ]);

    $notification = new LeaveDecisionNotification(LeaveRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]));

    expect($notification->via($this->user))->toBe(['database']);
});

it('defaults to mail + database with no preference row', function () {
    $notification = new LeaveDecisionNotification(LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id]));

    expect($notification->via($this->user))->toBe(['mail', 'database']);
});
