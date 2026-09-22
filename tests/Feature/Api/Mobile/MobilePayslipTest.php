<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Payroll\PayrollService;
use App\Services\SelfService\SelfServiceDashboard;
use App\Support\TenantContext;

beforeEach(function () {
    $this->travelTo('2026-09-21');
    $this->tenant = tenantWithRoles(['slug' => 'slip-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'payroll' => ['salary_basis' => 'fixed']]])->save();

    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'salary_amount_cents' => 8_000_000,
    ]);
    $this->colleague = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 9_000_000]);

    $service = app(PayrollService::class);
    $august = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 8)->create();
    $service->approve($service->run($august->fresh()), userForTenant($this->tenant, Role::PayrollManager));
    $september = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    $service->run($september->fresh()); // draft: not released yet

    $this->token = mobileToken($this->user);
    $this->released = Payslip::query()->where('employee_id', $this->employee->id)->whereRelation('run', 'status', 'approved')->firstOrFail();
    $this->draft = Payslip::query()->where('employee_id', $this->employee->id)->whereRelation('run', 'status', '!=', 'approved')->firstOrFail();
});

it('lists only released payslips with the same year-to-date totals as My HR', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/me/payslips')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $this->released->uuid)
        ->assertJsonPath('data.0.period', 'August 2026');

    $salary = app(SelfServiceDashboard::class)->salary($this->employee, 2026);

    expect($response->json('meta.ytd_net_cents'))->toBe($salary['ytd_net_cents'])
        ->and($response->json('meta.ytd_gross_cents'))->toBe($salary['ytd_gross_cents'])
        ->and($response->json('data.0.net_pay_cents'))->toBe($this->released->net_pay_cents);
});

it('shows a payslip breakdown without zero lines', function () {
    $this->released->lines()->create([
        'tenant_id' => $this->tenant->id, 'type' => 'earning', 'code' => 'ZERO', 'name' => 'Zero allowance',
        'amount_cents' => 0, 'is_taxable' => false, 'is_statutory' => false, 'sort_order' => 99,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/me/payslips/{$this->released->uuid}")
        ->assertOk()
        ->assertJsonPath('data.net_pay_cents', $this->released->net_pay_cents)
        ->assertJsonPath('data.period', 'August 2026');

    expect(collect($response->json('data.earnings'))->pluck('code'))->not->toContain('ZERO')
        ->and($response->json('data.earnings'))->not->toBeEmpty()
        ->and(collect($response->json('data.earnings'))->sum('amount_cents'))->toBe($this->released->gross_earnings_cents);
});

it('downloads the receipt PDF', function () {
    $document = $this->withToken($this->token)->get("/api/v1/me/payslips/{$this->released->uuid}/pdf");

    $document->assertOk()->assertDownload();
    expect($document->headers->get('content-type'))->toBe('application/pdf')
        ->and($document->getContent())->toStartWith('%PDF');
});

it('hides draft payslips and other people\'s payslips', function () {
    $theirs = Payslip::query()->where('employee_id', $this->colleague->id)->firstOrFail();

    $this->withToken($this->token)->getJson("/api/v1/me/payslips/{$this->draft->uuid}")->assertNotFound();
    $this->withToken($this->token)->get("/api/v1/me/payslips/{$this->draft->uuid}/pdf")->assertNotFound();
    $this->withToken($this->token)->getJson("/api/v1/me/payslips/{$theirs->uuid}")->assertNotFound();
});

it('keeps payslips inside the organisation', function () {
    $other = tenantWithRoles(['slug' => 'other-slip']);
    $outsider = userForTenant($other, Role::HrAdministrator);
    Employee::factory()->forTenant($other)->create(['user_id' => $outsider->id]);

    $this->withToken(mobileToken($outsider))
        ->getJson("/api/v1/me/payslips/{$this->released->uuid}")
        ->assertNotFound();
});
