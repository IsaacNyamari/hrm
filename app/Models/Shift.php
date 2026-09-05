<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $break_minutes
 * @property int $grace_minutes
 * @property array<int, int> $working_days
 * @property int|null $overtime_after_minutes
 * @property bool $is_night_shift
 * @property bool $is_default
 * @property bool $is_active
 */
class Shift extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ShiftFactory> */
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
            'working_days' => 'array',
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'overtime_after_minutes' => 'integer',
            'is_night_shift' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Scheduled length of a single shift in minutes, less the unpaid break.
     * Handles shifts that cross midnight.
     */
    public function scheduledMinutes(): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->start_time));
        [$eh, $em] = array_map('intval', explode(':', $this->end_time));

        $start = $sh * 60 + $sm;
        $end = $eh * 60 + $em;

        if ($end <= $start) {
            $end += 24 * 60;
        }

        return max(0, $end - $start - $this->break_minutes);
    }

    public function worksOn(int $isoWeekday): bool
    {
        return in_array($isoWeekday, $this->working_days, true);
    }
}
