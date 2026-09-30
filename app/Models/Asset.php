<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\AssetCategory;
use App\Enums\AssetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $asset_tag
 * @property string $name
 * @property AssetCategory $category
 * @property string|null $serial_number
 * @property string|null $description
 * @property AssetStatus $status
 * @property int|null $branch_id
 * @property CarbonImmutable|null $purchased_on
 * @property int|null $purchase_cost_cents
 * @property string $currency
 * @property CarbonImmutable|null $warranty_expires_on
 * @property string|null $notes
 * @property-read Branch|null $branch
 */
class Asset extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<AssetFactory> */
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
            'category' => AssetCategory::class,
            'status' => AssetStatus::class,
            'purchased_on' => 'immutable_date',
            'purchase_cost_cents' => 'integer',
            'warranty_expires_on' => 'immutable_date',
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

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('id');
    }

    /**
     * @return HasMany<AssetAssignment, $this>
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('status', 'active');
    }
}
