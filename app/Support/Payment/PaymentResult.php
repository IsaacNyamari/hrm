<?php

declare(strict_types=1);

namespace App\Support\Payment;

use App\Enums\PaymentStatus;

final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  string|null  $redirectUrl  Hosted checkout page the payer must visit (e.g. Paystack), if any.
     */
    public function __construct(
        public PaymentStatus $status,
        public string $reference,
        public ?string $externalId = null,
        public ?int $amountCents = null,
        public ?string $failureReason = null,
        public array $raw = [],
        public ?string $redirectUrl = null,
    ) {}

    public function successful(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }

    public function pending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
