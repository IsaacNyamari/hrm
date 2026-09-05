<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Notifications\DocumentExpiringNotification;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'exp-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator); // has employees.documents.view
    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
});

it('notifies the employee and HR when a document hits a reminder mark', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 1));
    Notification::fake();

    $type = DocumentType::factory()->forTenant($this->tenant)->create(['notify_before_days' => [30, 7]]);
    $doc = EmployeeDocument::factory()->forEmployee($this->employee)->create([
        'document_type_id' => $type->id,
        'expiry_date' => CarbonImmutable::now()->addDays(30)->toDateString(),
    ]);

    $this->artisan('documents:check-expiry')->assertSuccessful();

    Notification::assertSentTo($this->employeeUser, DocumentExpiringNotification::class);
    Notification::assertSentTo($this->hr, DocumentExpiringNotification::class);
    expect($doc->fresh()->expiry_reminders_sent)->toContain(30);

    CarbonImmutable::setTestNow();
});

it('does not send the same reminder twice', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 1));

    $doc = EmployeeDocument::factory()->forEmployee($this->employee)->create([
        'expiry_date' => CarbonImmutable::now()->addDays(14)->toDateString(),
        'expiry_reminders_sent' => [14],
    ]);

    Notification::fake();
    $this->artisan('documents:check-expiry')->assertSuccessful();
    Notification::assertNothingSent();

    CarbonImmutable::setTestNow();
});

it('lists expiring documents in the report, scoped to the tenant', function () {
    EmployeeDocument::factory()->forEmployee($this->employee)->create([
        'title' => 'Work Permit', 'expiry_date' => now()->addDays(20)->toDateString(),
    ]);
    EmployeeDocument::factory()->forEmployee($this->employee)->create([
        'title' => 'Old ID', 'expiry_date' => now()->addDays(400)->toDateString(),
    ]);
    $other = tenantWithRoles(['slug' => 'rival-exp-co']);
    EmployeeDocument::factory()->forEmployee(Employee::factory()->forTenant($other)->create())->create([
        'title' => 'Rival doc', 'expiry_date' => now()->addDays(10)->toDateString(),
    ]);

    $response = $this->actingAs($this->hr)
        ->getJson('http://exp-co.hrm.test/documents/expiring?days=60')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('title'))
        ->toContain('Work Permit')->not->toContain('Old ID')->not->toContain('Rival doc');
});

it('forbids the expiry report for a user without documents.view', function () {
    $viewer = userForTenant($this->tenant, Role::Supervisor);

    $this->actingAs($viewer)
        ->getJson('http://exp-co.hrm.test/documents/expiring')
        ->assertForbidden();
});
