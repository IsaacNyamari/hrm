<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\LeadKind;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quote request or contact message from the public website. Platform-level
 * (no tenant): the operator reviews it and may send a registration link.
 *
 * @property int $id
 * @property string $uuid
 * @property LeadKind $kind
 * @property LeadStatus $status
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property int|null $employee_count
 * @property string|null $plan_interest
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $invited_at
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LeadKind::class,
            'status' => LeadStatus::class,
            'employee_count' => 'integer',
            'invited_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RegistrationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(RegistrationInvitation::class);
    }
}
