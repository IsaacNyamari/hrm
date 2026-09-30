<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\TaxRuleType;
use Carbon\CarbonImmutable;
use Database\Factories\TaxRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $name
 * @property TaxRuleType $type
 * @property string $country
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property array<string, mixed> $config
 * @property bool $is_active
 */
class TaxRule extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<TaxRuleFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaxRuleType::class,
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The rule of a given type in force on $date.
     */
    public static function inForce(TaxRuleType $type, CarbonImmutable $date): ?self
    {
        return static::query()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString()))
            ->orderByDesc('effective_from')
            ->first();
    }
}
