<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $salary_structure_id
 * @property int $salary_component_id
 * @property int|null $amount_cents
 * @property float|null $rate
 * @property-read SalaryComponent $component
 */
class SalaryStructureComponent extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'rate' => 'float'];
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
