<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TenantStatus;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string|null $tax_number
 * @property string $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $logo_path
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string $currency
 * @property string $timezone
 * @property int $fiscal_year_start
 * @property string|null $custom_domain
 * @property TenantStatus $status
 * @property CarbonImmutable|null $trial_ends_at
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
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
            'status' => TenantStatus::class,
            'trial_ends_at' => 'immutable_datetime',
            'fiscal_year_start' => 'integer',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Employees of this tenant. The global TenantScope is removed here because
     * the parent record already *is* the tenant — otherwise the relation would
     * be double-constrained (and return nothing) whenever a different tenant is
     * bound to the context. Tenant-owned models with an inverse relation on
     * Tenant follow this same pattern.
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class)->withoutGlobalScope(TenantScope::class);
    }

    public function onTrial(): bool
    {
        return $this->status === TenantStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Whether the tenant may currently use the tenant application. Subscription
     * billing (P3) will extend this; for now it is trial + active state.
     */
    public function hasAppAccess(): bool
    {
        if ($this->status === TenantStatus::Trialing) {
            return $this->onTrial();
        }

        return $this->status === TenantStatus::Active;
    }

    public function trialDaysRemaining(): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) CarbonImmutable::now()->diffInDays($this->trial_ends_at, false));
    }
}
