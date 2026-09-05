<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseCategory: string
{
    case Travel = 'travel';
    case Accommodation = 'accommodation';
    case Meals = 'meals';
    case Mileage = 'mileage';
    case Supplies = 'supplies';
    case Training = 'training';
    case Communication = 'communication';
    case Medical = 'medical';
    case Entertainment = 'entertainment';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isMileage(): bool
    {
        return $this === self::Mileage;
    }
}
