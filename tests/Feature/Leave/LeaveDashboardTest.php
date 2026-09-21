<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Leave\Panel;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'lvd-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
    app(LeaveService::class)->adjustBalance($this->employee, $this->annual, CarbonImmutable::now()->year, 30);

    $this->hr = userForTenant($this->tenant, Role::HrManager); // leave.approve + leave.manage
});

it('approves a pending request from the dashboard', function () {
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $this->actingAs($this->hr);

    Livewire::test(Panel::class)
        ->call('approve', $request->uuid)
        ->assertDispatched('toast-show');

    expect($request->fresh()->status->value)->toBe('approved');
});

it('rejects a pending request from the dashboard', function () {
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $this->actingAs($this->hr);

    Livewire::test(Panel::class)->call('reject', $request->uuid);

    expect($request->fresh()->status->value)->toBe('rejected');
});

it('blocks a bystander from deciding a request through the dashboard', function () {
    $request = app(LeaveService::class)->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));
    $bystander = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($bystander);

    Livewire::test(Panel::class)
        ->call('approve', $request->uuid)
        ->assertDispatched('toast-show');

    expect($request->fresh()->status->value)->toBe('pending');
});

it('lets the requester cancel their own pending request from the dashboard and returns the balance', function () {
    $service = app(LeaveService::class);
    $request = $service->request($this->employee, $this->annual, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-07'));

    $this->actingAs($this->employeeUser);

    Livewire::test(Panel::class)->call('cancelRequest', $request->uuid);

    expect($request->fresh()->status->value)->toBe('cancelled')
        ->and($service->balanceFor($this->employee, $this->annual, 2026)->pending_days)->toBe(0.0);
});

it('creates a custom leave type from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Panel::class)
        ->call('startCreateType')
        ->set('typeForm.name', 'Sabbatical')
        ->set('typeForm.code', 'SABBATICAL')
        ->set('typeForm.default_days_per_year', '30')
        ->call('saveType')
        ->assertDispatched('toast-show')
        ->assertSet('showTypeForm', false);

    expect(LeaveType::query()->where('code', 'SABBATICAL')->exists())->toBeTrue();
});

it('edits a leave type from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Panel::class)
        ->call('startEditType', $this->annual->uuid)
        ->assertSet('typeForm.code', 'ANNUAL')
        ->set('typeForm.default_days_per_year', '25')
        ->call('saveType')
        ->assertDispatched('toast-show');

    expect((float) $this->annual->fresh()->default_days_per_year)->toBe(25.0);
});

it('deletes an unused leave type from the dashboard', function () {
    $type = LeaveType::factory()->forTenant($this->tenant)->create(['code' => 'UNUSED']);

    $this->actingAs($this->hr);

    Livewire::test(Panel::class)->call('removeType', $type->uuid);

    expect(LeaveType::query()->find($type->id))->toBeNull();
});

it('refuses to delete a leave type that has requests', function () {
    LeaveRequest::factory()->forEmployee($this->employee)->create(['leave_type_id' => $this->annual->id]);

    $this->actingAs($this->hr);

    Livewire::test(Panel::class)
        ->call('removeType', $this->annual->uuid)
        ->assertDispatched('toast-show');

    expect(LeaveType::query()->find($this->annual->id))->not->toBeNull();
});

it('forbids managing leave types from the dashboard without leave.manage', function () {
    $this->actingAs($this->employeeUser);

    Livewire::test(Panel::class)
        ->call('startCreateType')
        ->assertForbidden();
});
