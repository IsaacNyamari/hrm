<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Events\LeaveApproved;
use App\Events\LeaveRequested;
use App\Exceptions\LeaveException;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'leave-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->manager = Employee::factory()->forTenant($this->tenant)->create();
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $this->manager->id]);

    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    $this->service = app(LeaveService::class);
});

function requestLeave(LeaveService $service, Employee $employee, LeaveType $type, string $start, string $end, array $opts = [])
{
    return $service->request($employee, $type, CarbonImmutable::parse($start), CarbonImmutable::parse($end), $opts);
}

it('creates a pending request, builds the approval chain and reserves the balance', function () {
    Event::fake([LeaveRequested::class]);

    // ANNUAL accrual is monthly — top the balance up so the test is deterministic.
    $this->service->adjustBalance($this->employee, $this->annual, CarbonImmutable::now()->year, 30);

    $request = requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-09'); // Mon–Fri = 5

    expect($request->status)->toBe(LeaveStatus::Pending)
        ->and($request->days)->toBe(5.0)
        ->and($request->approvals()->pluck('approver_type')->all())->toBe(['manager', 'hr']);

    $balance = $this->service->balanceFor($this->employee, $this->annual, 2026);
    expect($balance->pending_days)->toBe(5.0);

    Event::assertDispatched(LeaveRequested::class);
});

it('excludes weekends and holidays from the day count', function () {
    Holiday::factory()->forTenant($this->tenant)->on('2026-10-07')->create();
    $this->service->adjustBalance($this->employee, $this->annual, 2026, 30);

    // Mon 5th – Fri 9th, minus the Wed 7th holiday = 4 days
    $request = requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-09');

    expect($request->days)->toBe(4.0);
});

it('supports half days', function () {
    $this->service->adjustBalance($this->employee, $this->annual, 2026, 30);

    $request = requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-06', [
        'half_day_start' => true,
    ]);

    expect($request->days)->toBe(1.5);
});

it('advances through the approval chain then deducts on final approval', function () {
    $this->service->adjustBalance($this->employee, $this->annual, 2026, 30);
    $request = requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-06');

    $hrUser = userForTenant($this->tenant, Role::HrManager);

    // level 1 is "manager" — HR permission also satisfies canActOn
    $this->service->decide($request, $hrUser, true);
    expect($request->fresh()->current_level)->toBe(2)
        ->and($request->fresh()->status)->toBe(LeaveStatus::Pending);

    $this->service->decide($request->fresh(), $hrUser, true);

    $request->refresh();
    $balance = $this->service->balanceFor($this->employee, $this->annual, 2026);
    expect($request->status)->toBe(LeaveStatus::Approved)
        ->and($balance->pending_days)->toBe(0.0)
        ->and($balance->used_days)->toBe(2.0);
});

it('releases the reserved balance on rejection', function () {
    $this->service->adjustBalance($this->employee, $this->annual, 2026, 30);
    $request = requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-06');
    $hrUser = userForTenant($this->tenant, Role::HrManager);

    $this->service->decide($request, $hrUser, false, 'Not enough cover');

    $request->refresh();
    $balance = $this->service->balanceFor($this->employee, $this->annual, 2026);
    expect($request->status)->toBe(LeaveStatus::Rejected)
        ->and($balance->pending_days)->toBe(0.0)
        ->and($balance->used_days)->toBe(0.0);
});

it('auto-approves a type that does not require approval', function () {
    Event::fake([LeaveApproved::class]);
    $type = LeaveType::factory()->forTenant($this->tenant)->autoApproved()->create(['default_days_per_year' => 30]);

    $request = requestLeave($this->service, $this->employee, $type, '2026-10-05', '2026-10-06');

    expect($request->status)->toBe(LeaveStatus::Approved)
        ->and($this->service->balanceFor($this->employee, $type, 2026)->used_days)->toBe(2.0);

    Event::assertDispatched(LeaveApproved::class);
});

it('rejects a request that exceeds the remaining balance', function () {
    $type = LeaveType::factory()->forTenant($this->tenant)->create(['default_days_per_year' => 2]);
    $this->service->balanceFor($this->employee, $type, 2026); // allocate 2

    requestLeave($this->service, $this->employee, $type, '2026-10-05', '2026-10-09'); // 5 > 2
})->throws(LeaveException::class);

it('rejects overlapping requests', function () {
    $this->service->adjustBalance($this->employee, $this->annual, 2026, 30);
    requestLeave($this->service, $this->employee, $this->annual, '2026-10-05', '2026-10-09');

    requestLeave($this->service, $this->employee, $this->annual, '2026-10-08', '2026-10-12');
})->throws(LeaveException::class);

it('enforces the minimum notice period', function () {
    $type = LeaveType::factory()->forTenant($this->tenant)->create(['min_notice_days' => 14, 'default_days_per_year' => 30]);

    requestLeave($this->service, $this->employee, $type, CarbonImmutable::now()->addDays(3)->toDateString(), CarbonImmutable::now()->addDays(4)->toDateString());
})->throws(LeaveException::class);

it('enforces gender restriction', function () {
    $this->employee->update(['gender' => Gender::Male]);
    $maternity = LeaveType::query()->where('code', 'MATERNITY')->firstOrFail();

    requestLeave($this->service, $this->employee->fresh(), $maternity, '2026-11-02', '2026-11-06');
})->throws(LeaveException::class);

it('requires an attachment when the type demands one', function () {
    $sick = LeaveType::query()->where('code', 'SICK')->firstOrFail();
    $this->service->adjustBalance($this->employee, $sick, 2026, 30);

    requestLeave($this->service, $this->employee, $sick, '2026-10-05', '2026-10-06');
})->throws(LeaveException::class);

it('routes straight to HR when the employee has no manager', function () {
    $this->service->adjustBalance($this->manager, $this->annual, 2026, 30);
    $request = requestLeave($this->service, $this->manager, $this->annual, '2026-10-05', '2026-10-06');

    expect($request->approvals()->pluck('approver_type')->all())->toBe(['hr']);
});
