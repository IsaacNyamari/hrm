<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\Role;
use App\Livewire\Platform\Settings;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Payment\GatewayCredentials;
use App\Services\Payment\PaymentManager;
use Livewire\Livewire;

it('defaults the active payment gateway to Paystack', function () {
    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Paystack);
});

it('lets a platform admin switch the active payment gateway and audits it', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    $this->get(route('platform.settings'))->assertOk()->assertSee('Payment gateway');

    Livewire::test(Settings::class)
        ->assertSet('paymentProvider', 'paystack')
        ->set('paymentProvider', 'mpesa')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Mpesa)
        ->and(AuditLog::query()->where('event', 'platform.settings.payment_provider_changed')->exists())->toBeTrue();
});

it('rejects a gateway that is not selectable', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Settings::class)
        ->set('paymentProvider', 'manual')
        ->call('save')
        ->assertHasErrors(['paymentProvider' => 'in']);

    expect(app(PaymentManager::class)->activeProvider())->toBe(PaymentProvider::Paystack);
});

it('forbids platform staff without settings permission', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create());

    $this->get(route('platform.settings'))->assertForbidden();
});

it('saves gateway credentials encrypted and the gateway uses them', function () {
    config(['paystack.secret_key' => null, 'paystack.public_key' => null]);
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Settings::class)
        ->set('paystack.public_key', 'pk_test_public123')
        ->set('paystack.secret_key', 'sk_test_secret456')
        ->call('saveCredentials', 'paystack')
        ->assertHasNoErrors()
        ->assertSet('paystack.secret_key', '')
        ->assertSee('Credentials configured');

    $raw = (array) PlatformSetting::query()->where('key', 'payments.paystack')->value('value');

    expect($raw['public_key'])->toBe('pk_test_public123')
        ->and($raw['secret_key'])->not->toContain('sk_test_secret456')
        ->and(app(GatewayCredentials::class)->for(PaymentProvider::Paystack)['secret_key'])->toBe('sk_test_secret456');

    app(PaymentManager::class)->gateway(PaymentProvider::Paystack);
    expect(config('paystack.secret_key'))->toBe('sk_test_secret456');
});

it('keeps a saved secret when the field is left blank', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());
    app(GatewayCredentials::class)->save(PaymentProvider::Paystack, ['public_key' => 'pk_test_a', 'secret_key' => 'sk_test_keep']);

    Livewire::test(Settings::class)
        ->set('paystack.public_key', 'pk_test_b')
        ->call('saveCredentials', 'paystack')
        ->assertHasNoErrors();

    $config = app(GatewayCredentials::class)->for(PaymentProvider::Paystack);

    expect($config['public_key'])->toBe('pk_test_b')->and($config['secret_key'])->toBe('sk_test_keep');
});

it('rejects keys that are not Paystack keys', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Settings::class)
        ->set('paystack.secret_key', 'not-a-key')
        ->call('saveCredentials', 'paystack')
        ->assertHasErrors(['paystack.secret_key' => 'starts_with']);
});

it('uses the matching Safaricom host for a stored M-Pesa environment', function () {
    app(GatewayCredentials::class)->save(PaymentProvider::Mpesa, [
        'environment' => 'production', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'shortcode' => '600000', 'passkey' => 'pk',
    ]);

    $config = app(GatewayCredentials::class)->for(PaymentProvider::Mpesa);

    expect($config['base_url'])->toBe('https://api.safaricom.co.ke')
        ->and(app(GatewayCredentials::class)->isConfigured(PaymentProvider::Mpesa))->toBeTrue();
});
