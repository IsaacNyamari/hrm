<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reimbursed = 'reimbursed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isPending(): bool
    {
        return $this === self::Submitted;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
