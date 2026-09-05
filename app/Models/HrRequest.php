<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\HrRequestStatus;
use App\Enums\HrRequestType;
use Carbon\CarbonImmutable;
use Database\Factories\HrRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $reference
 * @property int $employee_id
 * @property HrRequestType $type
 * @property string $subject
 * @property string|null $body
 * @property string $priority
 * @property HrRequestStatus $status
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution_note
 * @property-read Employee $employee
 */
class HrRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<HrRequestFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HrRequestType::class,
            'status' => HrRequestStatus::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<HrRequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(HrRequestComment::class)->orderBy('id');
    }
}
