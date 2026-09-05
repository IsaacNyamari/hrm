<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Notifications\LeaveDecisionNotification;
use App\Notifications\LeaveRequestedNotification;
use App\Services\Leave\LeaveService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'lv-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
    app(LeaveService::class)->adjustBalance($this->employee, $this->annual, CarbonImmutable::now()->year, 30);

    $this->hrUser = userForTenant($this->tenant, Role::HrManager);
});

it('lets an employee submit a request and notifies an approver', function () {
    Notification::fake();

    $this->actingAs($this->employeeUser)
        ->postJson('http://lv-co.hrm.test/leave-requests', [
            'leave_type_uuid' => $this->annual->uuid,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'reason' => 'Family trip',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.days', 3);

    Notification::assertSentTo($this->hrUser, LeaveRequestedNotification::class);
});

it('an employee only sees their own requests in the list', function () {
    app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $other = Employee::factory()->forTenant($this->tenant)->create();
    LeaveRequest::factory()->forEmployee($other)->count(2)->create(['leave_type_id' => $this->annual->id]);

    $this->actingAs($this->employeeUser)
        ->getJson('http://lv-co.hrm.test/leave-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->hrUser)
        ->getJson('http://lv-co.hrm.test/leave-requests')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('an approver can approve a request end to end and the employee is notified', function () {
    Notification::fake();
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    // chain here is just [hr] (employee has no manager)
    $this->actingAs($this->hrUser)
        ->postJson("http://lv-co.hrm.test/leave-requests/{$request->uuid}/approve", ['comment' => 'Approved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    Notification::assertSentTo($this->employeeUser, LeaveDecisionNotification::class);
});

it('forbids a random employee from deciding', function () {
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));
    $bystander = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($bystander)
        ->postJson("http://lv-co.hrm.test/leave-requests/{$request->uuid}/approve")
        ->assertForbidden();
});

it('lets the requester cancel a pending request and returns the balance', function () {
    $service = app(LeaveService::class);
    $request = $service->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $this->actingAs($this->employeeUser)
        ->postJson("http://lv-co.hrm.test/leave-requests/{$request->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($service->balanceFor($this->employee, $this->annual, 2026)->pending_days)->toBe(0.0);
});

it('cannot act on another tenant\'s leave request', function () {
    $other = tenantWithRoles(['slug' => 'rival-lv-co']);
    $otherEmployee = Employee::factory()->forTenant($other)->create();
    $victim = LeaveRequest::factory()->forEmployee($otherEmployee)->create([
        'leave_type_id' => LeaveType::factory()->forTenant($other)->create()->id,
    ]);

    $this->actingAs($this->hrUser)
        ->postJson("http://lv-co.hrm.test/leave-requests/{$victim->uuid}/approve")
        ->assertNotFound();
});

it('shows a leave balance summary', function () {
    $this->actingAs($this->employeeUser)
        ->getJson('http://lv-co.hrm.test/leave-balances')
        ->assertOk()
        ->assertJsonPath('data.year', CarbonImmutable::now()->year);
});

it('exposes approved leave and holidays on the calendar', function () {
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));
    app(LeaveService::class)->decide($request, $this->hrUser, true);
    Holiday::factory()->forTenant($this->tenant)->on('2026-10-20')->create(['name' => 'Mashujaa Day']);

    $this->actingAs($this->hrUser)
        ->getJson('http://lv-co.hrm.test/leave-calendar?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertJsonFragment(['type' => 'holiday', 'name' => 'Mashujaa Day'])
        ->assertJsonFragment(['type' => 'leave']);
});
