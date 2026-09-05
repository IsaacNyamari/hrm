<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\PayslipStatus;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $employee_number
 * @property string $currency
 * @property int $gross_earnings_cents
 * @property int $taxable_income_cents
 * @property int $statutory_deductions_cents
 * @property int $other_deductions_cents
 * @property int $total_deductions_cents
 * @property int $net_pay_cents
 * @property PayslipStatus $status
 * @property array<string, mixed>|null $snapshot
 * @property string|null $pdf_path
 * @property-read Employee $employee
 * @property-read PayrollRun $run
 */
class Payslip extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_earnings_cents' => 'integer',
            'taxable_income_cents' => 'integer',
            'statutory_deductions_cents' => 'integer',
            'other_deductions_cents' => 'integer',
            'total_deductions_cents' => 'integer',
            'net_pay_cents' => 'integer',
            'status' => PayslipStatus::class,
            'snapshot' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('type')->orderBy('sort_order');
    }
}
