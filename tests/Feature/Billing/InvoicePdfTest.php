<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->plan = Plan::factory()->create();
    $this->tenant = tenantWithRoles(['slug' => 'pdf-co']);
    $this->invoice = Invoice::factory()->forTenant($this->tenant)->create(['number' => 'INV-7-000001']);
    $this->invoice->items()->create(['description' => 'Starter plan', 'quantity' => 1, 'unit_amount_cents' => 250000, 'amount_cents' => 250000]);
});

function assertPdfDownload($response, string $filename): void
{
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($filename);

    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
}

it('lets a tenant owner download their invoice as a pdf', function () {
    Subscription::factory()->forTenant($this->tenant)->onPlan($this->plan)->create();
    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $this->actingAs($owner)
        ->get('http://pdf-co.hrm.test/billing')
        ->assertSee(route('billing.invoices.pdf', $this->invoice), false);

    assertPdfDownload(
        $this->actingAs($owner)->get('http://pdf-co.hrm.test/billing/invoices/'.$this->invoice->uuid.'/pdf'),
        'invoice-inv-7-000001.pdf',
    );
});

it('still allows the download when the subscription has lapsed', function () {
    Subscription::factory()->forTenant($this->tenant)->onPlan($this->plan)->expired()->create();

    assertPdfDownload(
        $this->actingAs(userForTenant($this->tenant, Role::TenantOwner))
            ->get('http://pdf-co.hrm.test/billing/invoices/'.$this->invoice->uuid.'/pdf'),
        'invoice-inv-7-000001.pdf',
    );
});

it('forbids tenant users without billing.view', function () {
    Subscription::factory()->forTenant($this->tenant)->onPlan($this->plan)->create();

    $this->actingAs(userForTenant($this->tenant, Role::Employee))
        ->get('http://pdf-co.hrm.test/billing/invoices/'.$this->invoice->uuid.'/pdf')
        ->assertForbidden();
});

it('hides another tenant\'s invoice', function () {
    $other = tenantWithRoles(['slug' => 'other-pdf-co']);
    Subscription::factory()->forTenant($other)->onPlan($this->plan)->create();

    $this->actingAs(userForTenant($other, Role::TenantOwner))
        ->get('http://other-pdf-co.hrm.test/billing/invoices/'.$this->invoice->uuid.'/pdf')
        ->assertNotFound();
});

it('lets platform operators with invoice or payment access download any invoice', function (Role $role) {
    $operator = User::factory()->platformAdmin($role)->create();

    assertPdfDownload(
        $this->actingAs($operator)->get(route('platform.billing.invoices.pdf', $this->invoice)),
        'invoice-inv-7-000001.pdf',
    );
})->with([Role::SuperAdmin, Role::FinanceAdmin, Role::SupportAdmin]);

it('keeps tenant users out of the platform invoice download', function () {
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner))
        ->get(route('platform.billing.invoices.pdf', $this->invoice))
        ->assertForbidden();
});
