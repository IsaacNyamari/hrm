<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\CalculationMethod;
use App\Enums\SalaryComponentType;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property string $code
 * @property SalaryComponentType $type
 * @property CalculationMethod $calculation_method
 * @property int|null $amount_cents
 * @property float|null $rate
 * @property string|null $base_code
 * @property string|null $formula
 * @property bool $is_taxable
 * @property bool $is_pensionable
 * @property bool $is_statutory
 * @property bool $is_recurring
 * @property int $sort_order
 * @property bool $is_active
 */
class SalaryComponent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SalaryComponentFactory> */
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
            'type' => SalaryComponentType::class,
            'calculation_method' => CalculationMethod::class,
            'amount_cents' => 'integer',
            'rate' => 'float',
            'is_taxable' => 'boolean',
            'is_pensionable' => 'boolean',
            'is_statutory' => 'boolean',
            'is_recurring' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
