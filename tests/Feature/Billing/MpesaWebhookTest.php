<?php

declare(strict_types=1);

use App\Actions\Billing\ChargeInvoice;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Events\PaymentFailed;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.mpesa' => [
            'environment' => 'sandbox',
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
            'shortcode' => '174379',
            'passkey' => 'pk',
            'callback_url' => 'https://hrm.test/api/webhooks/mpesa',
            'callback_token' => 'shhh',
        ],
    ]);

    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'ResponseCode' => '0',
            'CheckoutRequestID' => 'ws_CO_TEST_1',
            'MerchantRequestID' => 'mr_1',
            'ResponseDescription' => 'Success',
        ]),
    ]);

    $this->tenant = tenantWithRoles();
    $this->plan = Plan::factory()->create(['price_cents' => 500000]);

    [$this->subscription, $this->invoice, $this->payment] = app(SubscriptionManager::class)
        ->subscribe($this->tenant, $this->plan, PaymentProvider::Mpesa, '254700111222');

    app(ChargeInvoice::class)->handle($this->payment);
});

function mpesaCallback(string $checkoutId, int $resultCode, int $amount = 5000): array
{
    $callback = [
        'MerchantRequestID' => 'mr_1',
        'CheckoutRequestID' => $checkoutId,
        'ResultCode' => $resultCode,
        'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user',
    ];

    if ($resultCode === 0) {
        $callback['CallbackMetadata'] = ['Item' => [
            ['Name' => 'Amount', 'Value' => $amount],
            ['Name' => 'MpesaReceiptNumber', 'Value' => 'QGH7XYZ'],
            ['Name' => 'PhoneNumber', 'Value' => 254700111222],
        ]];
    }

    return ['Body' => ['stkCallback' => $callback]];
}

it('records the STK push external reference on the payment', function () {
    expect($this->payment->fresh()->provider_reference)->toBe('ws_CO_TEST_1')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('settles the payment, pays the invoice and activates the subscription on a successful callback', function () {
    $this->postJson('/api/webhooks/mpesa?token=shhh', mpesaCallback('ws_CO_TEST_1', 0))
        ->assertOk()
        ->assertJsonPath('ResultCode', 0);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($this->invoice->fresh()->isPaid())->toBeTrue()
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($this->tenant->fresh()->hasAppAccess())->toBeTrue();
});

it('is idempotent for a duplicated callback', function () {
    $payload = mpesaCallback('ws_CO_TEST_1', 0);

    $this->postJson('/api/webhooks/mpesa?token=shhh', $payload)->assertOk();
    $this->postJson('/api/webhooks/mpesa?token=shhh', $payload)->assertOk();

    expect(PaymentTransaction::where('type', 'webhook')->where('external_id', 'ws_CO_TEST_1')->count())->toBe(1)
        ->and($this->invoice->fresh()->amount_paid_cents)->toBe(500000);
});

it('rejects an unverified callback', function () {
    $this->postJson('/api/webhooks/mpesa', mpesaCallback('ws_CO_TEST_1', 0))
        ->assertStatus(401);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('marks the payment failed and fires an event on a failed callback', function () {
    Event::fake([PaymentFailed::class]);

    $this->postJson('/api/webhooks/mpesa?token=shhh', mpesaCallback('ws_CO_TEST_1', 1032))
        ->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->invoice->fresh()->isPaid())->toBeFalse();

    Event::assertDispatched(PaymentFailed::class);
});
