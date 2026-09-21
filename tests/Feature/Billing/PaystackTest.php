<?php

declare(strict_types=1);

use App\Actions\Billing\ChargeInvoice;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Livewire\Billing\Overview;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Services\Payment\PaymentManager;
use App\Services\Subscription\SubscriptionManager;
use App\Support\TenantContext;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'tenancy.central_domains' => ['hrm.test'],
        'paystack.secret_key' => 'sk_test_secret',
        'paystack.url' => 'https://api.paystack.co',
    ]);

    $this->plan = Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'price_cents' => 250000]);
    $this->tenant = tenantWithRoles(['slug' => 'paystack-co']);
    Subscription::factory()->forTenant($this->tenant)->onPlan($this->plan)->trialing(-2)->create();
});

/**
 * Subscribe the tenant through Paystack and return the pending payment.
 */
function paystackPayment(object $test): Payment
{
    Http::fake(['*/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc', 'reference' => 'x'],
    ])]);

    [, , $payment] = app(SubscriptionManager::class)
        ->subscribe($test->tenant, $test->plan, PaymentProvider::Paystack, 'owner@paystack-co.test');

    app(ChargeInvoice::class)->handle($payment);

    return $payment->fresh();
}

function signedPaystackWebhook(object $test, array $payload, string $secret = 'sk_test_secret'): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return $test->call('POST', '/api/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, $secret),
    ], $body);
}

it('pays through Paystack by default without offering tenants a choice', function () {
    app(TenantContext::class)->set($this->tenant);
    $owner = userForTenant($this->tenant, Role::TenantOwner);
    $owner->forceFill(['email' => 'owner@paystack-co.test'])->save();
    $this->actingAs($owner);

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->assertSet('payer', 'owner@paystack-co.test')
        ->assertSee('Continue to Paystack')
        ->assertDontSee('M-Pesa (Daraja)')
        ->assertDontSee('M-Pesa phone number');
});

it('pays through M-Pesa when the platform admin makes it active', function () {
    PlatformSetting::put(PaymentManager::ACTIVE_PROVIDER_SETTING, 'mpesa');
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->assertSet('payer', '')
        ->assertSee('M-Pesa phone number')
        ->assertDontSee('Continue to Paystack');
});

it('sends the payer to Paystack checkout when subscribing', function () {
    Http::fake(['*/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'access_code' => 'xyz', 'reference' => 'ignored'],
    ])]);
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->set('payer', 'billing@paystack-co.test')
        ->call('subscribe')
        ->assertHasNoErrors()
        ->assertRedirect('https://checkout.paystack.com/xyz');

    $payment = Payment::query()->where('tenant_id', $this->tenant->id)->latest('id')->first();

    expect($payment->provider)->toBe(PaymentProvider::Paystack)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->payer)->toBe('billing@paystack-co.test');

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/transaction/initialize')
        && $request->hasHeader('Authorization', 'Bearer sk_test_secret')
        && $request['email'] === 'billing@paystack-co.test'
        && $request['amount'] === $payment->amount_cents
        && $request['reference'] === $payment->reference
        && $request['callback_url'] === route('billing.paystack.callback'));
});

it('ignores a tenant-supplied provider on the JSON subscribe endpoint', function () {
    Http::fake(['*/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/api1', 'access_code' => 'api1', 'reference' => 'x'],
    ])]);

    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner))
        ->postJson('http://paystack-co.hrm.test/subscription', ['plan' => 'starter', 'provider' => 'mpesa'])
        ->assertCreated()
        ->assertJsonPath('data.payment.provider', 'paystack')
        ->assertJsonPath('data.payment.checkout_url', 'https://checkout.paystack.com/api1');
});

it('requires a valid billing email for Paystack', function () {
    app(TenantContext::class)->set($this->tenant);
    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner));

    Livewire::test(Overview::class)
        ->call('choosePlan', 'starter')
        ->set('payer', 'not-an-email')
        ->call('subscribe')
        ->assertHasErrors(['payer' => 'email']);
});

it('verifies the charge on return from checkout and activates the subscription', function () {
    $payment = paystackPayment($this);

    Http::fake(['*/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['id' => 987654, 'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount_cents, 'currency' => 'KES'],
    ])]);

    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner))
        ->get('http://paystack-co.hrm.test/billing/paystack/callback?reference='.$payment->reference)
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing.status', ['success', 'Payment received — your subscription is active.']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->provider_reference)->toBe('987654')
        ->and($payment->invoice->fresh()->isPaid())->toBeTrue()
        ->and($this->tenant->subscription()->first()->status)->toBe(SubscriptionStatus::Active);
});

it('does not trust an unpaid checkout return', function () {
    $payment = paystackPayment($this);

    Http::fake(['*/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['id' => 111, 'status' => 'abandoned', 'reference' => $payment->reference, 'amount' => $payment->amount_cents, 'gateway_response' => 'The transaction was not completed'],
    ])]);

    $this->actingAs(userForTenant($this->tenant, Role::TenantOwner))
        ->get('http://paystack-co.hrm.test/billing/paystack/callback?reference='.$payment->reference)
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing.status', ['danger', 'The transaction was not completed']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->invoice->fresh()->isPaid())->toBeFalse();
});

it('settles a payment from a signed charge.success webhook exactly once', function () {
    $payment = paystackPayment($this);
    $payload = ['event' => 'charge.success', 'data' => [
        'id' => 555, 'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount_cents, 'currency' => 'KES',
    ]];

    signedPaystackWebhook($this, $payload)->assertOk()->assertJson(['status' => true]);
    signedPaystackWebhook($this, $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->invoice->fresh()->isPaid())->toBeTrue()
        ->and(PaymentTransaction::query()->where('provider', 'paystack')->where('external_id', '555')->count())->toBe(1);
});

it('rejects a webhook with an invalid signature', function () {
    $payment = paystackPayment($this);

    signedPaystackWebhook($this, ['event' => 'charge.success', 'data' => [
        'id' => 556, 'reference' => $payment->reference, 'amount' => $payment->amount_cents,
    ]], 'forged-secret')->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('acknowledges unrelated webhook events without touching payments', function () {
    $payment = paystackPayment($this);

    signedPaystackWebhook($this, ['event' => 'transfer.success', 'data' => ['id' => 777, 'reference' => $payment->reference]])
        ->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentTransaction::query()->where('provider', 'paystack')->where('type', 'webhook')->exists())->toBeFalse();
});

it('fails the payment gracefully when Paystack is not configured', function () {
    config(['paystack.secret_key' => null]);

    [, , $payment] = app(SubscriptionManager::class)
        ->subscribe($this->tenant, $this->plan, PaymentProvider::Paystack, 'owner@paystack-co.test');

    $result = app(ChargeInvoice::class)->handle($payment);

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

it('keeps the package proxy routes and generic hook disabled', function () {
    $this->getJson('/api/paystack/balance')->assertForbidden();
    $this->postJson('/api/paystack/hooks', [])->assertForbidden();
});
