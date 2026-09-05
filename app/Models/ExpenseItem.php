<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\ExpenseCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property int $expense_id
 * @property string $description
 * @property ExpenseCategory $category
 * @property CarbonImmutable $spent_on
 * @property int $amount_cents
 * @property float|null $quantity
 * @property int|null $unit_rate_cents
 * @property string|null $receipt_disk
 * @property string|null $receipt_path
 * @property string|null $notes
 * @property-read Expense $expense
 */
class ExpenseItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExpenseItemFactory> */
    use HasFactory;

    use HasUuid;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'spent_on' => 'immutable_date',
            'amount_cents' => 'integer',
            'quantity' => 'float',
            'unit_rate_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
