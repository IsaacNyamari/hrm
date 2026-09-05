<?php

declare(strict_types=1);

namespace App\Enums;

enum PerformanceCycleType: string
{
    case Annual = 'annual';
    case Biannual = 'biannual';
    case Quarterly = 'quarterly';
    case Monthly = 'monthly';
    case Probation = 'probation';
    case Project = 'project';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
