<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Database\Factories\SalaryStructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
class SalaryStructure extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SalaryStructureFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<SalaryStructureComponent, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }
}
