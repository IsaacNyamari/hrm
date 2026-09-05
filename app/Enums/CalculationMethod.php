<?php

declare(strict_types=1);

namespace App\Enums;

enum CalculationMethod: string
{
    case Fixed = 'fixed';           // a set amount
    case Percentage = 'percentage'; // a rate applied to a named base
    case Formula = 'formula';       // a safe expression over payroll variables

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
