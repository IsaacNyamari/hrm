<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Billing\Overview;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Services\Payment\PaymentManager;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->plan = Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'price_cents' => 250000]);
    $this->tenant = tenantWithRoles(['slug' => 'lapsed-page-co']);
    Subscription::factory()->forTenant($this->tenant)->onPlan($this->plan)->trialing(-2)->create();
});

it('renders the billing page instead of json when a trial has ended', function () {
    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $this->actingAs($owner)
        ->get('http://lapsed-page-co.hrm.test/employees')
        ->assertRedirect(route('billing.index'));

    $this->actingAs($owner)
        ->get('http://lapsed-page-co.hrm.test/billing')
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSeeLivewire(Overview::class)
        ->assertSee('Your free trial has ended')
        ->assertSee('Renew plan');
});

it('tells users without billing access to contact their administrator', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->get('http://lapsed-page-co.hrm.test/billing')
        ->assertOk()
        ->assertSee('Your free trial has ended')
        ->assertSee('Please contact your administrator')
        ->assertDontSee('Renew plan');
});

it('subscribes to a plan and sends an M-Pesa STK push', function () {
    PlatformSetting::put(PaymentManager::ACTIVE_PROVIDER_SETTING, 'mpesa');
    config(['services.mpesa' => [
        'environment' => 'sandbox',
        'base_url' => 'https://sandbox.safaricom.co.ke',
        'consumer_key' => 'ck',
        'consumer_secret' => 'cs',
        'shortcode' => '174379',
        'passkey' => 'pk',
        'callback_url' => 'https://hrm.test/api/webhooks/mpesa',
        'callback_token' => 'shhh',
    ]]);
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'ResponseCode' => '0',
            'CheckoutRequestID' => 'ws_CO_PAGE_1',
            'MerchantRequestID' => 'mr_1',
            'ResponseDescription' => 'Success',
        ]),
    ]);
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->set('payer', '0712 345 678')
        ->call('subscribe')
        ->assertHasNoErrors()
        ->assertSet('showPayment', false);

    $payment = Payment::query()->where('tenant_id', $this->tenant->id)->latest('id')->first();

    expect($payment->payer)->toBe('254712345678')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider_reference)->toBe('ws_CO_PAGE_1');
});

it('fails the payment gracefully when M-Pesa rejects the credentials', function () {
    PlatformSetting::put(PaymentManager::ACTIVE_PROVIDER_SETTING, 'mpesa');
    config(['services.mpesa' => [
        'base_url' => 'https://sandbox.safaricom.co.ke',
        'consumer_key' => 'wrong',
        'consumer_secret' => 'wrong',
        'shortcode' => '174379',
        'passkey' => 'pk',
        'callback_url' => 'https://hrm.test/api/webhooks/mpesa',
        'callback_token' => null,
    ]]);
    Http::fake(['*/oauth/v1/generate*' => Http::response(['errorMessage' => 'Invalid credentials'], 400)]);
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->set('payer', '0712345678')
        ->call('subscribe')
        ->assertOk()
        ->assertHasNoErrors();

    $payment = Payment::query()->where('tenant_id', $this->tenant->id)->latest('id')->first();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('The payment provider is unavailable right now. Please try again later.');
});

it('rejects an invalid M-Pesa number', function () {
    PlatformSetting::put(PaymentManager::ACTIVE_PROVIDER_SETTING, 'mpesa');
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->set('payer', '12345')
        ->call('subscribe')
        ->assertHasErrors(['payer' => 'regex']);

    expect(Payment::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

it('forbids subscribing without subscription.manage', function () {
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::FinanceManager));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->assertForbidden();
});
