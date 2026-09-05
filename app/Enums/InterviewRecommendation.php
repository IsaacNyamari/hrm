<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewRecommendation: string
{
    case StrongYes = 'strong_yes';
    case Yes = 'yes';
    case No = 'no';
    case StrongNo = 'strong_no';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Numeric weight for aggregating a panel's recommendations (-2..2).
     */
    public function score(): int
    {
        return match ($this) {
            self::StrongYes => 2,
            self::Yes => 1,
            self::No => -1,
            self::StrongNo => -2,
        };
    }
}
