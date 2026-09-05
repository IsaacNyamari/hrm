<?php

declare(strict_types=1);

namespace App\Enums;

enum SalaryComponentType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function sign(): int
    {
        return $this === self::Earning ? 1 : -1;
    }
}
