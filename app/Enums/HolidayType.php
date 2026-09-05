<?php

declare(strict_types=1);

namespace App\Enums;

enum HolidayType: string
{
    case Public = 'public';
    case Company = 'company';
    case Custom = 'custom';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
