<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $payslip_id
 * @property SalaryComponentType $type
 * @property string $code
 * @property string $name
 * @property int $amount_cents
 * @property bool $is_taxable
 * @property bool $is_statutory
 * @property int $sort_order
 */
class PayslipLine extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'amount_cents' => 'integer',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
