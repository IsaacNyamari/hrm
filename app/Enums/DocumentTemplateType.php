<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentTemplateType: string
{
    case EmploymentLetter = 'employment_letter';
    case PromotionLetter = 'promotion_letter';
    case SalaryIncrement = 'salary_increment';
    case ConfirmationLetter = 'confirmation_letter';
    case WarningLetter = 'warning_letter';
    case TerminationLetter = 'termination_letter';
    case RecommendationLetter = 'recommendation_letter';
    case Custom = 'custom';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
