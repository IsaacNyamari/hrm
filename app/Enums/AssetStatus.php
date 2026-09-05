<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case InRepair = 'in_repair';
    case Retired = 'retired';
    case Lost = 'lost';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isAssignable(): bool
    {
        return $this === self::Available;
    }
}
