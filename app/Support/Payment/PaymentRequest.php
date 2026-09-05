<?php

declare(strict_types=1);

namespace App\Support\Payment;

final readonly class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $reference,
        public ?string $payer = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
