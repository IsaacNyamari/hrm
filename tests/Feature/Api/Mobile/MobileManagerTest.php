<?php

declare(strict_types=1);

use App\Enums\AttendanceSource;
use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Attendance\AttendanceService;
use App\Services\Leave\LeaveService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00'));
    $this->tenant = tenantWithRoles(['slug' => 'boss-co']);
    app(TenantContext::class)->set($this->tenant);

    // A line manager with only the Employee role, and one report.
    $this->managerUser = userForTenant($this->tenant, Role::Employee);
    $this->manager = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->managerUser->id]);
    $this->reportUser = userForTenant($this->tenant, Role::Employee);
    $this->report = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->reportUser->id, 'manager_id' => $this->manager->id, 'first_name' => 'Amani', 'last_name' => 'Otieno',
    ]);
    $this->hr = userForTenant($this->tenant, Role::HrManager);

    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    app(LeaveService::class)->adjustBalance($this->report, $annual, 2026, 21);
    $this->request = app(LeaveService::class)->request($this->report, $annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-06'), [], $this->reportUser);
});

it('shows a line manager their report\'s request at the manager step, and HR every pending request', function () {
    asMobileUser($this->managerUser)
        ->getJson('/api/v1/approvals/leave')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $this->request->uuid)
        ->assertJsonPath('data.0.employee', 'Amani Otieno');

    asMobileUser($this->hr)->getJson('/api/v1/approvals/leave')->assertJsonCount(1, 'data');

    // A colleague with no reports and no approval permission sees nothing.
    asMobileUser($this->reportUser)->getJson('/api/v1/approvals/leave')->assertJsonCount(0, 'data');
});

it('lets the line manager approve with a note, moving the request up the chain', function () {
    asMobileUser($this->managerUser)
        ->postJson("/api/v1/approvals/leave/{$this->request->uuid}/approve", ['comment' => 'Enjoy'])
        ->assertOk();

    $fresh = $this->request->fresh(['approvals']);
    expect($fresh->approvals->firstWhere('level', 1)->comment)->toBe('Enjoy');

    // HR finishes it.
    asMobileUser($this->hr)
        ->postJson("/api/v1/approvals/leave/{$this->request->uuid}/approve")
        ->assertOk();

    expect($this->request->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('lets HR reject', function () {
    asMobileUser($this->hr)
        ->postJson("/api/v1/approvals/leave/{$this->request->uuid}/reject", ['comment' => 'Month-end close'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_note', 'Month-end close');
});

it('forbids deciding someone you do not manage and hides other organisations', function () {
    asMobileUser($this->reportUser)
        ->postJson("/api/v1/approvals/leave/{$this->request->uuid}/approve")
        ->assertForbidden();

    $outsider = userForTenant(tenantWithRoles(['slug' => 'boss-rival']), Role::HrManager);
    asMobileUser($outsider)
        ->postJson("/api/v1/approvals/leave/{$this->request->uuid}/approve")
        ->assertNotFound();
});

it('shows team attendance today to attendance staff only', function () {
    app(AttendanceService::class)->clockIn($this->report, new PunchData(AttendanceSource::Biometric));

    asMobileUser($this->hr)
        ->getJson('/api/v1/team/attendance/today')
        ->assertOk()
        ->assertJsonPath('data.stats.present', 1)
        ->assertJsonPath('data.records.0.employee', 'Amani Otieno')
        ->assertJsonPath('data.channel', 'tenant.'.$this->tenant->id.'.attendance');

    asMobileUser($this->reportUser)->getJson('/api/v1/team/attendance/today')->assertForbidden();
});

it('searches the staff directory within the organisation for users who can view employees', function () {
    $other = tenantWithRoles(['slug' => 'dir-rival']);
    Employee::factory()->forTenant($other)->create(['first_name' => 'Amani', 'last_name' => 'Outsider']);
    app(TenantContext::class)->set($this->tenant);

    $response = asMobileUser($this->hr)
        ->getJson('/api/v1/directory?search=Amani')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Amani Otieno')
        ->assertJsonMissingPath('data.0.salary_amount_cents');

    asMobileUser($this->hr)
        ->getJson('/api/v1/directory/'.$response->json('data.0.uuid'))
        ->assertOk()
        ->assertJsonPath('data.manager', $this->manager->fullName());

    asMobileUser($this->reportUser)->getJson('/api/v1/directory')->assertForbidden();
});
