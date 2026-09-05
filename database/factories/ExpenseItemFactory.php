<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseItem>
 */
class ExpenseItemFactory extends Factory
{
    protected $model = ExpenseItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'expense_id' => Expense::factory(),
            'description' => fake()->sentence(3),
            'category' => fake()->randomElement([ExpenseCategory::Meals, ExpenseCategory::Supplies, ExpenseCategory::Communication, ExpenseCategory::Other]),
            'spent_on' => now()->subDays(2)->toDateString(),
            'amount_cents' => fake()->numberBetween(500, 50000),
        ];
    }

    public function forExpense(Expense $expense): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $expense->tenant_id,
            'expense_id' => $expense->getKey(),
        ]);
    }
}
