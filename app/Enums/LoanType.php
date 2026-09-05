<?php

declare(strict_types=1);

namespace App\Enums;

enum LoanType: string
{
    case Loan = 'loan';
    case SalaryAdvance = 'salary_advance';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
