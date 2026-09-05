<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Notifications\HrRequestSubmittedNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'hrr-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('lets an employee submit an HR request and notifies HR', function () {
    Notification::fake();

    $this->actingAs($this->employeeUser)
        ->postJson('http://hrr-co.hrm.test/hr-requests', [
            'type' => 'salary_certificate',
            'subject' => 'Certificate for visa application',
            'body' => 'Travelling next month.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.reference', 'HR-000001');

    Notification::assertSentTo($this->hr, HrRequestSubmittedNotification::class);
});

it('scopes the list: employee sees own, HR sees all', function () {
    HrRequest::factory()->forEmployee($this->employee)->create();
    HrRequest::factory()->forEmployee(Employee::factory()->forTenant($this->tenant)->create())->count(2)->create();

    $this->actingAs($this->employeeUser)->getJson('http://hrr-co.hrm.test/hr-requests')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->hr)->getJson('http://hrr-co.hrm.test/hr-requests')->assertOk()->assertJsonCount(3, 'data');
});

it('lets HR assign and resolve a request', function () {
    $request = HrRequest::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->hr)
        ->putJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}", [
            'assigned_to' => $this->hr->id, 'status' => 'resolved', 'resolution_note' => 'Issued.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    expect($request->fresh()->resolved_at)->not->toBeNull();
});

it('hides internal comments from the employee', function () {
    $request = HrRequest::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->hr)->postJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}/comments", [
        'body' => 'Internal: check payroll first', 'internal' => true,
    ])->assertCreated();
    $this->actingAs($this->hr)->postJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}/comments", [
        'body' => 'We are on it', 'internal' => false,
    ])->assertCreated();

    $employeeView = $this->actingAs($this->employeeUser)->getJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}")->assertOk();
    expect(collect($employeeView->json('data.comments'))->pluck('body'))
        ->toContain('We are on it')->not->toContain('Internal: check payroll first');

    $hrView = $this->actingAs($this->hr)->getJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}")->assertOk();
    expect($hrView->json('data.comments'))->toHaveCount(2);
});

it('cannot touch another tenant\'s HR request', function () {
    $other = tenantWithRoles(['slug' => 'rival-hrr-co']);
    $request = HrRequest::factory()->forEmployee(Employee::factory()->forTenant($other)->create())->create();

    $this->actingAs($this->hr)
        ->putJson("http://hrr-co.hrm.test/hr-requests/{$request->uuid}", ['status' => 'closed'])
        ->assertNotFound();
});
