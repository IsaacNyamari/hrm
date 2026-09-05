<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseDecided
{
    use Dispatchable;

    public function __construct(
        public readonly Expense $expense,
        public readonly bool $approved,
    ) {}
}
