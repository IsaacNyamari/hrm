<?php

declare(strict_types=1);

namespace App\Enums;

enum PayslipStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
