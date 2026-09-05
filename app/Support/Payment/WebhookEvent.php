<?php

declare(strict_types=1);

namespace App\Support\Payment;

use App\Enums\PaymentStatus;

final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public PaymentStatus $status,
        public bool $verified,
        public ?string $reference = null,
        public ?string $externalId = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $failureReason = null,
        public array $payload = [],
    ) {}
}
