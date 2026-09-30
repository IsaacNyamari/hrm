<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Models\PlatformSetting;
use App\Services\Payment\Gateways\ManualGateway;
use App\Services\Payment\Gateways\MpesaGateway;
use App\Services\Payment\Gateways\PaystackGateway;
use InvalidArgumentException;

class PaymentManager
{
    public function __construct(private readonly GatewayCredentials $credentials) {}

    public function gateway(PaymentProvider|string $provider): PaymentGateway
    {
        $key = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return match ($key) {
            PaymentProvider::Paystack->value => $this->paystack(),
            PaymentProvider::Mpesa->value => new MpesaGateway($this->credentials->for(PaymentProvider::Mpesa)),
            PaymentProvider::Manual->value => new ManualGateway,
            default => throw new InvalidArgumentException("No payment gateway registered for [{$key}]."),
        };
    }

    /**
     * Whether payments made through this provider now are real money. Paystack
     * test keys (sk_test_...) and the M-Pesa sandbox move no funds, so those
     * payments must never count as collected revenue.
     */
    public function isLive(PaymentProvider|string $provider): bool
    {
        $provider = $provider instanceof PaymentProvider ? $provider : PaymentProvider::from($provider);
        $config = $this->credentials->for($provider);

        return match ($provider) {
            PaymentProvider::Paystack => str_starts_with((string) ($config['secret_key'] ?? ''), 'sk_live_'),
            PaymentProvider::Mpesa => ($config['environment'] ?? 'sandbox') === 'production',
            default => true,
        };
    }

    public const ACTIVE_PROVIDER_SETTING = 'billing.payment_provider';

    public function default(): PaymentGateway
    {
        return $this->gateway($this->activeProvider());
    }

    /**
     * The gateway tenants pay through. Chosen by the platform admin (Platform →
     * Settings); until one is chosen, `billing.default_provider` (Paystack).
     */
    public function activeProvider(): PaymentProvider
    {
        $chosen = PlatformSetting::get(self::ACTIVE_PROVIDER_SETTING);

        if (is_string($chosen) && in_array($chosen, $this->selectableProviders(), true)) {
            return PaymentProvider::from($chosen);
        }

        return PaymentProvider::from((string) config('billing.default_provider', PaymentProvider::Paystack->value));
    }

    /**
     * Providers the platform admin may make active.
     *
     * @return array<int, string>
     */
    public function selectableProviders(): array
    {
        return (array) config('billing.providers', [PaymentProvider::Paystack->value]);
    }

    /**
     * The Paystack package reads config('paystack.*') directly, so the
     * effective (admin-edited) credentials are applied to config first.
     */
    private function paystack(): PaystackGateway
    {
        $config = $this->credentials->for(PaymentProvider::Paystack);
        config(['paystack' => $config]);

        return new PaystackGateway($config);
    }
}
