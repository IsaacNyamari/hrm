<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Expo push token registered by the mobile app for a signed-in user.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $expo_push_token
 * @property string $platform
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use BelongsToTenant, HasFactory;

    public const PLATFORMS = ['ios', 'android'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
