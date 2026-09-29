<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\CallStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $created_by
 * @property CallStatus $status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property-read User $creator
 * @property-read CallParticipant[]|Collection $participants
 */
class Call extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CallFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CallParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }
}
