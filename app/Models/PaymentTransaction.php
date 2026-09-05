<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only provider event log. Not tenant-scoped: webhook handlers write here
 * before a tenant is known. The (provider, external_id) unique key makes webhook
 * processing idempotent.
 *
 * @property int $id
 * @property int|null $payment_id
 * @property int|null $tenant_id
 * @property string $provider
 * @property string $type
 * @property string|null $status
 * @property string|null $external_id
 * @property int|null $amount_cents
 * @property string|null $currency
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $processed_at
 */
class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
