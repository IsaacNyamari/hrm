<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property string $bank_name
 * @property string|null $branch_name
 * @property string $account_name
 * @property string $account_number decrypted transparently
 * @property string|null $swift_code
 * @property bool $is_primary
 */
class EmployeeBankAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Last four digits only — for lists / payslips.
     */
    public function maskedAccountNumber(): string
    {
        $number = $this->account_number;

        return str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
