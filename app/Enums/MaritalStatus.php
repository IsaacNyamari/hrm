<?php

declare(strict_types=1);

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Separated = 'separated';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
