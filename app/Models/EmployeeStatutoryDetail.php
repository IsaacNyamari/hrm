<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-to-one with Employee. Statutory identifiers are encrypted at rest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string|null $national_id
 * @property string|null $tax_pin
 * @property string|null $nssf_number
 * @property string|null $shif_number
 * @property string|null $nhif_number
 * @property string|null $pension_number
 * @property string|null $helb_number
 */
class EmployeeStatutoryDetail extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'national_id' => 'encrypted',
            'tax_pin' => 'encrypted',
            'nssf_number' => 'encrypted',
            'shif_number' => 'encrypted',
            'nhif_number' => 'encrypted',
            'pension_number' => 'encrypted',
            'helb_number' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
