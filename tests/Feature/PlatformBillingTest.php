<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Platform\Billing as PlatformBilling;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceSentNotification;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->superAdmin = User::factory()->platformAdmin(Role::SuperAdmin)->create();
});

it('redirects guests away from platform billing', function () {
    $this->get(route('platform.billing'))->assertRedirect(route('login'));
});

it('forbids a tenant user from platform billing', function () {
    $tenant = tenantWithRoles();
    $this->actingAs(userForTenant($tenant, Role::TenantOwner))
        ->get(route('platform.billing'))
        ->assertForbidden();
});

it('lists payments from every organization', function () {
    $a = Tenant::factory()->create(['name' => 'Alpha Org']);
    $b = Tenant::factory()->create(['name' => 'Beta Org']);
    Payment::factory()->forTenant($a)->succeeded()->create(['reference' => 'PAY-ALPHA-1', 'amount_cents' => 300000]);
    Payment::factory()->forTenant($b)->create(['reference' => 'PAY-BETA-1', 'status' => PaymentStatus::Failed]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing'))
        ->assertOk()
        ->assertSee('PAY-ALPHA-1')
        ->assertSee('PAY-BETA-1')
        ->assertSee('Alpha Org');
});

it('filters payments by status', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-OK']);
    Payment::factory()->forTenant($tenant)->create(['reference' => 'PAY-PENDING', 'status' => PaymentStatus::Pending]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['status' => PaymentStatus::Succeeded->value]))
        ->assertOk()
        ->assertSee('PAY-OK')
        ->assertDontSee('PAY-PENDING');
});

it('ignores an unknown status filter instead of failing', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-VISIBLE']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['status' => 'not-a-status']))
        ->assertOk()
        ->assertSee('PAY-VISIBLE');
});

it('shows a single payment with its organization', function () {
    $tenant = Tenant::factory()->create(['name' => 'Detail Org']);
    $payment = Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-DETAIL']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing.payments.show', $payment))
        ->assertOk()
        ->assertSee('PAY-DETAIL')
        ->assertSee('Detail Org');
});

it('shows invoices to a manager and forbids a support admin', function () {
    $tenant = Tenant::factory()->create();
    Invoice::factory()->forTenant($tenant)->create(['number' => 'INV-ACROSS']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['tab' => 'invoices']))
        ->assertOk()
        ->assertSee('INV-ACROSS');

    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();
    $this->actingAs($support)
        ->get(route('platform.billing', ['tab' => 'invoices']))
        ->assertForbidden();
});

it('shows subscriptions to a manager and forbids a support admin', function () {
    $tenant = Tenant::factory()->create(['name' => 'Sub Org']);
    $plan = Plan::factory()->create(['name' => 'Growth']);
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->create();

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['tab' => 'subscriptions']))
        ->assertOk()
        ->assertSee('Sub Org')
        ->assertSee('Growth');

    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();
    $this->actingAs($support)
        ->get(route('platform.billing', ['tab' => 'subscriptions']))
        ->assertForbidden();
});

it('shows payment history on the organization detail page', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-ONTENANT', 'amount_cents' => 450000]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee('PAY-ONTENANT');
});

it('counts only live payments as collected revenue and labels test payments', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-LIVE', 'amount_cents' => 700000, 'is_live' => true]);
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-SANDBOX', 'amount_cents' => 1250000, 'is_live' => false]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['tab' => 'payments']))
        ->assertOk()
        ->assertSeeText('7,000')
        ->assertDontSeeText('19,500')
        ->assertSee('PAY-SANDBOX')
        ->assertSee('Test');
});

it('treats Paystack test keys and the M-Pesa sandbox as not live', function () {
    $gateways = app(PaymentManager::class);

    config(['paystack.secret_key' => 'sk_test_abc']);
    expect($gateways->isLive(PaymentProvider::Paystack))->toBeFalse();

    config(['paystack.secret_key' => 'sk_live_abc']);
    expect($gateways->isLive(PaymentProvider::Paystack))->toBeTrue();

    config(['services.mpesa.environment' => 'sandbox']);
    expect($gateways->isLive(PaymentProvider::Mpesa))->toBeFalse();
});

it('emails an invoice with its PDF to the organization owner', function () {
    Notification::fake();
    $tenant = tenantWithRoles(['slug' => 'inv-send-co']);
    $owner = userForTenant($tenant, Role::TenantOwner);
    $staff = userForTenant($tenant, Role::HrManager);
    $invoice = Invoice::factory()->forTenant($tenant)->create();

    Livewire::actingAs($this->superAdmin)
        ->test(PlatformBilling::class)
        ->set('tab', 'invoices')
        ->call('sendInvoice', $invoice->uuid)
        ->assertSet('noticeVariant', 'success');

    Notification::assertSentTo($owner, InvoiceSentNotification::class);
    Notification::assertNotSentTo($staff, InvoiceSentNotification::class);
});

it('renders the invoice email with the PDF attached', function () {
    $tenant = tenantWithRoles(['slug' => 'inv-mail-co']);
    $owner = userForTenant($tenant, Role::TenantOwner);
    $invoice = Invoice::factory()->forTenant($tenant)->create();

    $mail = (new InvoiceSentNotification($invoice))->toMail($owner);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe('invoice-'.Str::slug($invoice->number).'.pdf');
});

it('deletes an unpaid invoice but keeps a paid one', function () {
    $tenant = tenantWithRoles(['slug' => 'inv-del-co']);
    $open = Invoice::factory()->forTenant($tenant)->create();
    $paid = Invoice::factory()->forTenant($tenant)->paid()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(PlatformBilling::class)
        ->set('tab', 'invoices')
        ->call('deleteInvoice', $open->uuid)
        ->call('deleteInvoice', $paid->uuid)
        ->assertSet('noticeVariant', 'warning');

    expect(Invoice::query()->whereKey($open->id)->exists())->toBeFalse()
        ->and(Invoice::query()->whereKey($paid->id)->exists())->toBeTrue();
});

it('forbids a support admin from sending or deleting invoices', function () {
    $tenant = tenantWithRoles(['slug' => 'inv-forbid-co']);
    $invoice = Invoice::factory()->forTenant($tenant)->create();
    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();

    Livewire::actingAs($support)->test(PlatformBilling::class)->call('sendInvoice', $invoice->uuid)->assertForbidden();
    Livewire::actingAs($support)->test(PlatformBilling::class)->call('deleteInvoice', $invoice->uuid)->assertForbidden();
});
