<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'authz-co', 'name' => 'Authz Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->staffUser = userForTenant($this->tenant, Role::Employee);
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->staffUser->id]);
});

/**
 * A plain Employee-role user must be refused every administrative action even
 * though they can authenticate and reach the tenant.
 */
it('refuses an Employee-role user administrative endpoints', function (string $method, string $uri, array $body = []) {
    $this->actingAs($this->staffUser)
        ->json($method, "http://authz-co.hrm.test/{$uri}", $body)
        ->assertStatus(403);
})->with([
    'create employee' => ['post', 'employees', ['first_name' => 'X', 'last_name' => 'Y']],
    'create branch' => ['post', 'branches', ['name' => 'HQ']],
    'create department' => ['post', 'departments', ['name' => 'Ops']],
    'create leave type' => ['post', 'leave-types', ['name' => 'Study', 'code' => 'STUDY']],
    'create tax rule' => ['post', 'tax-rules', ['type' => 'paye']],
    'create vacancy' => ['post', 'vacancies', ['title' => 'Dev']],
    'create performance cycle' => ['post', 'performance-cycles', ['name' => 'C', 'type' => 'annual', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01']],
    'create document template' => ['post', 'document-templates', ['name' => 'T', 'type' => 'custom', 'subject' => 'S', 'body' => 'B']],
    'create webhook endpoint' => ['post', 'webhook-endpoints', ['name' => 'W', 'url' => 'https://e.test']],
    'read audit log' => ['get', 'audit-logs', []],
    'read branding' => ['get', 'branding', []],
    'run a report' => ['get', 'reports/payroll-summary', []],
]);

it('still lets that user reach their own self-service surfaces', function () {
    $this->actingAs($this->staffUser)->getJson('http://authz-co.hrm.test/me/dashboard')->assertOk();
    $this->actingAs($this->staffUser)->getJson('http://authz-co.hrm.test/notifications/feed')->assertOk();
});
