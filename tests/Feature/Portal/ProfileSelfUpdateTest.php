<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\EmployeeProfileUpdated;
use App\Models\Employee;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'prof-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'first_name' => 'Original', 'salary_amount_cents' => 5_000_000,
    ]);
});

it('lets an employee update their own whitelisted fields', function () {
    Event::fake([EmployeeProfileUpdated::class]);

    $this->actingAs($this->user)
        ->putJson('http://prof-co.hrm.test/me/profile', [
            'phone' => '+254700111222',
            'city' => 'Nakuru',
            'personal_email' => 'me@personal.test',
            'emergency_contacts' => [
                ['name' => 'Kin One', 'phone' => '254700999888', 'relationship' => 'Sibling'],
            ],
            'bank_account' => ['bank_name' => 'Co-op', 'account_name' => 'Original Name', 'account_number' => '5566778899'],
        ])
        ->assertOk()
        ->assertJsonPath('data.phone', '+254700111222')
        ->assertJsonPath('data.city', 'Nakuru');

    $this->employee->refresh();
    expect($this->employee->phone)->toBe('+254700111222')
        ->and($this->employee->emergencyContacts()->count())->toBe(1)
        ->and($this->employee->bankAccounts()->where('is_primary', true)->count())->toBe(1);

    Event::assertDispatched(EmployeeProfileUpdated::class, fn (EmployeeProfileUpdated $e) => $e->bySelf === true);
});

it('ignores fields outside the self-service whitelist', function () {
    $this->actingAs($this->user)
        ->putJson('http://prof-co.hrm.test/me/profile', [
            'first_name' => 'Hacked',
            'salary_amount_cents' => 99_000_000,
            'phone' => '+254711000000',
        ])
        ->assertOk();

    $this->employee->refresh();
    expect($this->employee->first_name)->toBe('Original')
        ->and($this->employee->salary_amount_cents)->toBe(5_000_000)
        ->and($this->employee->phone)->toBe('+254711000000');
});

it('422s a user with no linked employee', function () {
    $orphan = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($orphan)
        ->putJson('http://prof-co.hrm.test/me/profile', ['phone' => '123'])
        ->assertStatus(422);
});
