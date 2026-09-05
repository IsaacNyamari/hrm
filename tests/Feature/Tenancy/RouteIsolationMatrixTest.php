<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\GeneratedLetter;
use App\Models\PerformanceCycle;
use App\Models\Vacancy;
use App\Models\WebhookEndpoint;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenantA = tenantWithRoles(['slug' => 'iso-a', 'name' => 'Iso A']);
    $this->tenantB = tenantWithRoles(['slug' => 'iso-b', 'name' => 'Iso B']);
    app(TenantContext::class)->set($this->tenantA);
    $this->userA = userForTenant($this->tenantA, Role::TenantOwner);
});

/**
 * A resource that belongs to tenant B must never be reachable by uuid from
 * tenant A — implicit route-model binding is scoped, so it 404s (not 403).
 */
it('404s a cross-tenant record on its show route', function (string $path, Closure $makeForeign) {
    /** @var Model $foreign */
    $foreign = $makeForeign($this->tenantB);

    $this->actingAs($this->userA)
        ->getJson("http://iso-a.hrm.test/{$path}/{$foreign->getRouteKey()}")
        ->assertNotFound();
})->with([
    'employees' => ['employees', fn ($t) => Employee::factory()->forTenant($t)->create()],
    'vacancies' => ['vacancies', fn ($t) => Vacancy::factory()->forTenant($t)->create()],
    'assets' => ['assets', fn ($t) => Asset::factory()->forTenant($t)->create()],
    'performance-cycles' => ['performance-cycles', fn ($t) => PerformanceCycle::factory()->forTenant($t)->create()],
    'expense-claims' => ['expense-claims', fn ($t) => Expense::factory()->forEmployee(Employee::factory()->forTenant($t)->create())->create()],
    'letters' => ['letters', fn ($t) => GeneratedLetter::factory()->forEmployee(Employee::factory()->forTenant($t)->create())->create()],
    'audit-logs' => ['audit-logs', fn ($t) => AuditLog::factory()->forTenant($t)->create()],
]);

it('does not surface another tenant\'s rows on list endpoints', function (string $path, Closure $seed) {
    $seed($this->tenantB);

    $data = $this->actingAs($this->userA)
        ->getJson("http://iso-a.hrm.test/{$path}")
        ->assertOk()
        ->json('data');

    expect($data)->toBeArray()->toBeEmpty();
})->with([
    'employees' => ['employees', fn ($t) => Employee::factory()->forTenant($t)->count(3)->create()],
    'vacancies' => ['vacancies', fn ($t) => Vacancy::factory()->forTenant($t)->count(3)->create()],
    'assets' => ['assets', fn ($t) => Asset::factory()->forTenant($t)->count(3)->create()],
    'webhook-endpoints' => ['webhook-endpoints', fn ($t) => WebhookEndpoint::factory()->forTenant($t)->count(3)->create()],
    'audit-logs' => ['audit-logs', fn ($t) => AuditLog::factory()->forTenant($t)->count(3)->create()],
]);
