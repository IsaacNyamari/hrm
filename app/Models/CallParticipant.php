<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\CallParticipantStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CallParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $call_id
 * @property int $user_id
 * @property CallParticipantStatus $status
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $left_at
 * @property-read Call $call
 * @property-read User $user
 */
class CallParticipant extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CallParticipantFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CallParticipantStatus::class,
            'invited_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Call, $this>
     */
    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
