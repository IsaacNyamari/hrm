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
    public function gateway(PaymentProvider|string $provider): PaymentGateway
    {
        $key = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return match ($key) {
            PaymentProvider::Paystack->value => new PaystackGateway((array) config('paystack', [])),
            PaymentProvider::Mpesa->value => new MpesaGateway((array) config('services.mpesa', [])),
            PaymentProvider::Manual->value => new ManualGateway,
            default => throw new InvalidArgumentException("No payment gateway registered for [{$key}]."),
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
}
