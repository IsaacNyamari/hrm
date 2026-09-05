<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Expense;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'exp-co', 'name' => 'Exp Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->finance = userForTenant($this->tenant, Role::FinanceManager); // expenses.approve + .manage
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->finance->id]);

    $this->managerUser = userForTenant($this->tenant, Role::Employee);
    $this->manager = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->managerUser->id]);

    $this->staffUser = userForTenant($this->tenant, Role::Employee);
    $this->staff = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->staffUser->id, 'manager_id' => $this->manager->id,
    ]);

    $this->draft = function (): string {
        return $this->actingAs($this->staffUser)
            ->postJson('http://exp-co.hrm.test/expense-claims', ['title' => 'Trip to Kisumu'])
            ->assertCreated()->json('data.uuid');
    };

    $this->addItem = function (string $uuid, array $overrides = []): void {
        $this->actingAs($this->staffUser)
            ->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/items", array_merge([
                'description' => 'Bus fare', 'category' => 'communication',
                'spent_on' => now()->subDay()->toDateString(), 'amount_cents' => 4500,
            ], $overrides))
            ->assertCreated();
    };
});

it('totals a claim from its line items', function () {
    $uuid = ($this->draft)();
    ($this->addItem)($uuid, ['amount_cents' => 4500]);
    ($this->addItem)($uuid, ['amount_cents' => 5500, 'description' => 'Lunch', 'category' => 'meals']);

    $this->actingAs($this->staffUser)->getJson("http://exp-co.hrm.test/expense-claims/{$uuid}")
        ->assertOk()->assertJsonPath('data.total_cents', 10000);
});

it('prices a mileage line from the configured rate', function () {
    config(['expenses.mileage_rate_cents_per_km' => 6000]);
    $uuid = ($this->draft)();

    $this->actingAs($this->staffUser)
        ->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/items", [
            'description' => 'Drive to site', 'category' => 'mileage',
            'spent_on' => now()->subDay()->toDateString(), 'quantity' => 12,
        ])
        ->assertCreated();

    expect(Expense::query()->where('uuid', $uuid)->firstOrFail()->total_cents)->toBe(72000);
});

it('will not submit an empty claim', function () {
    $uuid = ($this->draft)();

    $this->actingAs($this->staffUser)
        ->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/submit")
        ->assertStatus(422);
});

it('routes a submitted claim manager -> finance -> approved -> reimbursed', function () {
    $uuid = ($this->draft)();
    ($this->addItem)($uuid);

    $this->actingAs($this->staffUser)->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/submit")
        ->assertOk()->assertJsonPath('data.status', 'submitted');

    $this->actingAs($this->managerUser)->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/approve")
        ->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.current_level', 2);

    $this->actingAs($this->finance)->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $this->actingAs($this->finance)
        ->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/reimburse", ['payment_reference' => 'MPESA123'])
        ->assertOk()->assertJsonPath('data.status', 'reimbursed');
});

it('blocks a stranger from approving a claim', function () {
    $uuid = ($this->draft)();
    ($this->addItem)($uuid);
    $this->actingAs($this->staffUser)->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/submit")->assertOk();

    $stranger = userForTenant($this->tenant, Role::Employee);
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/approve")->assertForbidden();
});

it('requires a receipt for travel line items before submitting', function () {
    $uuid = ($this->draft)();
    ($this->addItem)($uuid, ['category' => 'travel', 'description' => 'Flight', 'amount_cents' => 90000]);

    $this->actingAs($this->staffUser)
        ->postJson("http://exp-co.hrm.test/expense-claims/{$uuid}/submit")
        ->assertStatus(422);
});

it('keeps claims scoped to their tenant', function () {
    $other = tenantWithRoles(['slug' => 'rival-exp-co']);
    $claim = Expense::factory()->forEmployee(Employee::factory()->forTenant($other)->create())->create();

    $this->actingAs($this->finance)
        ->getJson("http://exp-co.hrm.test/expense-claims/{$claim->uuid}")
        ->assertNotFound();
});
