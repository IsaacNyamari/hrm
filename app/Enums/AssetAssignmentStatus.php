<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetAssignmentStatus: string
{
    case Active = 'active';
    case Returned = 'returned';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
