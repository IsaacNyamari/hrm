<?php

declare(strict_types=1);

namespace App\Support\Payment;

use App\Enums\PaymentStatus;

final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PaymentStatus $status,
        public string $reference,
        public ?string $externalId = null,
        public ?int $amountCents = null,
        public ?string $failureReason = null,
        public array $raw = [],
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
