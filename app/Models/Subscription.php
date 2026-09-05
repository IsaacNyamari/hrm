<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_start
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $payment_provider
 * @property string|null $external_subscription_id
 * @property array<string, mixed>|null $metadata
 * @property-read Plan $plan
 * @property-read Tenant $tenant
 */
class Subscription extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function onGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function grantsAppAccess(): bool
    {
        if ($this->status === SubscriptionStatus::Trialing) {
            return $this->onTrial();
        }

        if ($this->status === SubscriptionStatus::Cancelled) {
            return $this->onGracePeriod();
        }

        return $this->status === SubscriptionStatus::Active;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && $this->current_period_end !== null
            && $this->current_period_end->isPast();
    }
}
