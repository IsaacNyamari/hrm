<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Services\Leave\LeaveService;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'me-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'salary_amount_cents' => 8_000_000,
    ]);
});

it('returns an aggregated self-service dashboard', function () {
    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    app(LeaveService::class)->adjustBalance($this->employee, $annual, CarbonImmutable::now()->year, 21);
    app(LeaveService::class)->request($this->employee, $annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    app(PayrollService::class)->run($period);

    Announcement::factory()->forTenant($this->tenant)->create(['title' => 'All hands Friday']);

    $response = $this->actingAs($this->user)
        ->getJson('http://me-co.hrm.test/me/dashboard')
        ->assertOk();

    $response->assertJsonPath('data.pending_leave', 1)
        ->assertJsonPath('data.employee.number', $this->employee->employee_number);

    expect($response->json('data.leave_balances'))->not->toBeEmpty()
        ->and($response->json('data.recent_payslips'))->toHaveCount(1)
        ->and(collect($response->json('data.announcements'))->pluck('title'))->toContain('All hands Friday');
});

it('422s a user with no linked employee record', function () {
    $orphan = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($orphan)
        ->getJson('http://me-co.hrm.test/me/dashboard')
        ->assertStatus(422);
});
