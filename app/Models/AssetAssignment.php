<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\AssetAssignmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AssetAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $asset_id
 * @property int $employee_id
 * @property CarbonImmutable $assigned_on
 * @property CarbonImmutable|null $due_back_on
 * @property CarbonImmutable|null $returned_on
 * @property AssetAssignmentStatus $status
 * @property string|null $condition_out
 * @property string|null $condition_in
 * @property string|null $notes
 * @property int|null $assigned_by
 * @property-read Asset $asset
 * @property-read Employee $employee
 */
class AssetAssignment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetAssignmentFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_on' => 'immutable_date',
            'due_back_on' => 'immutable_date',
            'returned_on' => 'immutable_date',
            'status' => AssetAssignmentStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
