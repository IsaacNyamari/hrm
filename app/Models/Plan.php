<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\BillingInterval;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property BillingInterval $billing_interval
 * @property int|null $employee_limit
 * @property int|null $storage_limit_mb
 * @property array<string, mixed> $features
 * @property int $trial_days
 * @property bool $is_active
 * @property int $sort_order
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'features' => 'array',
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'employee_limit' => 'integer',
            'storage_limit_mb' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features['flags'] ?? [], true);
    }

    public function limit(string $key): ?int
    {
        return match ($key) {
            'employees' => $this->employee_limit,
            'storage_mb' => $this->storage_limit_mb,
            default => $this->features['limits'][$key] ?? null,
        };
    }

    public function isUnlimited(string $key): bool
    {
        return $this->limit($key) === null;
    }
}
