<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Services\Payment\Gateways\ManualGateway;
use App\Services\Payment\Gateways\MpesaGateway;
use InvalidArgumentException;

class PaymentManager
{
    public function gateway(PaymentProvider|string $provider): PaymentGateway
    {
        $key = $provider instanceof PaymentProvider ? $provider->value : $provider;

        return match ($key) {
            PaymentProvider::Mpesa->value => new MpesaGateway((array) config('services.mpesa', [])),
            PaymentProvider::Manual->value => new ManualGateway,
            default => throw new InvalidArgumentException("No payment gateway registered for [{$key}]."),
        };
    }

    public function default(): PaymentGateway
    {
        return $this->gateway((string) config('billing.default_provider', PaymentProvider::Mpesa->value));
    }
}
