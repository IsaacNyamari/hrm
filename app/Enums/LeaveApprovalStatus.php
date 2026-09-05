<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
