<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentProvider: string
{
    case Paystack = 'paystack';
    case Mpesa = 'mpesa';
    case AirtelMoney = 'airtel_money';
    case Pesapal = 'pesapal';
    case Card = 'card';
    case Bank = 'bank';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Paystack => 'Paystack',
            self::Mpesa => 'M-Pesa',
            self::AirtelMoney => 'Airtel Money',
            self::Pesapal => 'Pesapal',
            self::Card => 'Card',
            self::Bank => 'Bank transfer',
            self::Manual => 'Manual / offline',
        };
    }
}
