<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RegistrationInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use link that lets an approved prospect create their organization.
 * Only the SHA-256 of the token is stored. The link is consumed the moment it
 * is first opened; the opener then has {@see self::SESSION_MINUTES} minutes to
 * finish registering in that browser session.
 *
 * @property int $id
 * @property int|null $lead_id
 * @property string $email
 * @property string|null $name
 * @property string|null $company
 * @property string $token_hash
 * @property int|null $invited_by
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $tenant_id
 */
class RegistrationInvitation extends Model
{
    /** @use HasFactory<RegistrationInvitationFactory> */
    use HasFactory;

    public const SESSION_MINUTES = 60;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->first();
    }

    /**
     * Never opened, never used, not past its expiry.
     */
    public function canBeOpened(): bool
    {
        return $this->opened_at === null && $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Opened (in this session), not yet used, and still inside the window.
     */
    public function canCompleteRegistration(): bool
    {
        return $this->opened_at !== null
            && $this->used_at === null
            && $this->opened_at->copy()->addMinutes(self::SESSION_MINUTES)->isFuture();
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
