<?php

declare(strict_types=1);

use App\Facades\Tenancy;
use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Asset;
use App\Models\AttendanceDevice;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\JobPosition;
use App\Models\LeaveType;
use App\Models\PerformanceCycle;
use App\Models\Shift;
use App\Models\TaxRule;
use App\Models\Vacancy;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->tenantA = tenantWithRoles(['slug' => 'scope-a']);
    $this->tenantB = tenantWithRoles(['slug' => 'scope-b']);
});

/**
 * Every tenant-owned model must return only the bound tenant's rows through its
 * global scope, and — for models whose factory fills tenant_id from context —
 * auto-stamp tenant_id on create.
 */
it('scopes reads to the bound tenant', function (string $model) {
    /** @var class-string<Model> $model */
    $model::factory()->forTenant($this->tenantA)->count(2)->create();
    $model::factory()->forTenant($this->tenantB)->count(3)->create();

    $seenByA = Tenancy::run($this->tenantA, fn (): int => $model::query()->count());
    $seenByB = Tenancy::run($this->tenantB, fn (): int => $model::query()->count());
    $unscoped = $model::query()->withoutTenantScope()->count();

    // >= because provisioning seeds some of these (leave types, tax rules, etc.)
    expect($seenByA)->toBeGreaterThanOrEqual(2)
        ->and($seenByB)->toBeGreaterThanOrEqual(3)
        ->and($unscoped)->toBe($seenByA + $seenByB);
})->with([
    Announcement::class,
    Applicant::class,
    Asset::class,
    AttendanceDevice::class,
    Branch::class,
    Department::class,
    Designation::class,
    DocumentType::class,
    Employee::class,
    Holiday::class,
    JobPosition::class,
    LeaveType::class,
    PerformanceCycle::class,
    Shift::class,
    TaxRule::class,
    Vacancy::class,
    WebhookEndpoint::class,
]);

it('auto-stamps tenant_id from the bound context on direct create', function () {
    $branch = Tenancy::run($this->tenantA, fn () => Branch::query()->create([
        'name' => 'Auto HQ',
        'code' => 'AUTO-HQ',
    ]));

    expect($branch->tenant_id)->toBe($this->tenantA->id);
});
