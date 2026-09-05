<?php

declare(strict_types=1);

namespace App\Enums;

enum KpiDirection: string
{
    case HigherIsBetter = 'higher_is_better';
    case LowerIsBetter = 'lower_is_better';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
