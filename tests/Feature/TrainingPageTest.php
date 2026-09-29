<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'training-co']);
    app(TenantContext::class)->set($this->tenant);
});

it('requires a logged-in user to view the training page', function () {
    $this->get('http://training-co.hrm.test/training')->assertRedirect(route('login'));
});

it('lets any authenticated tenant user view the training page', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->get('http://training-co.hrm.test/training')
        ->assertOk()
        ->assertSee('Training & help centre')
        ->assertSee('Attendance & biometric devices')
        ->assertSee('Tasks');
});
