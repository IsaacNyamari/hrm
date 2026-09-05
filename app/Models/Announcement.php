<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $title
 * @property string $body
 * @property string $audience
 * @property int|null $department_id
 * @property int|null $branch_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $expires_at
 * @property int|null $published_by
 */
class Announcement extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Published, not expired, and relevant to $employee (or company-wide).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleTo(Builder $query, ?Employee $employee): void
    {
        $now = CarbonImmutable::now();

        $query->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(function (Builder $q) use ($employee): void {
                $q->where('audience', 'all');

                if ($employee?->department_id !== null) {
                    $q->orWhere(fn (Builder $inner) => $inner->where('audience', 'department')->where('department_id', $employee->department_id));
                }

                if ($employee?->branch_id !== null) {
                    $q->orWhere(fn (Builder $inner) => $inner->where('audience', 'branch')->where('branch_id', $employee->branch_id));
                }
            });
    }
}
