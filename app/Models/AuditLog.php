<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only audit trail. Rows are written once and never changed or removed
 * from application code — the `updating` / `deleting` guards enforce it.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $uuid
 * @property string $event
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $description
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $via_impersonation
 * @property CarbonImmutable|null $created_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    use HasUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit log entries are immutable.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit log entries cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'via_impersonation' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
