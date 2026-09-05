<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Processed = 'processed';
    case Approved = 'approved';
    case Paid = 'paid';
    case Locked = 'locked';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
