<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\AttendanceDeviceType;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered attendance-capture device. The app never speaks to hardware —
 * devices POST punches to the ingestion webhook authenticated by `secret`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int|null $branch_id
 * @property string $name
 * @property AttendanceDeviceType $type
 * @property string|null $serial_number
 * @property string $secret
 * @property array<string, mixed>|null $settings
 * @property bool $is_active
 * @property CarbonImmutable|null $last_seen_at
 */
class AttendanceDevice extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceDeviceFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttendanceDeviceType::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
            'secret' => 'encrypted',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
