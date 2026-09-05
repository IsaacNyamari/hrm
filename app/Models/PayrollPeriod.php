<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\PayrollFrequency;
use App\Enums\PayrollPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property PayrollFrequency $frequency
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property CarbonImmutable $pay_date
 * @property PayrollPeriodStatus $status
 */
class PayrollPeriod extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => PayrollFrequency::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'pay_date' => 'immutable_date',
            'status' => PayrollPeriodStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function latestRun(): ?PayrollRun
    {
        return $this->runs()->latest('id')->first();
    }
}
