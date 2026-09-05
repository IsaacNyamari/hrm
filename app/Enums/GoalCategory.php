<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalCategory: string
{
    case Business = 'business';
    case ProfessionalDevelopment = 'professional_development';
    case Behavioural = 'behavioural';
    case Operational = 'operational';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
