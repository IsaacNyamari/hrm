<?php

declare(strict_types=1);

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case Probation = 'probation';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
    case Resigned = 'resigned';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Probation => 'Probation',
            self::OnLeave => 'On leave',
            self::Suspended => 'Suspended',
            self::Terminated => 'Terminated',
            self::Resigned => 'Resigned',
            self::Retired => 'Retired',
        };
    }

    public function isCurrentlyEmployed(): bool
    {
        return match ($this) {
            self::Active, self::Probation, self::OnLeave, self::Suspended => true,
            self::Terminated, self::Resigned, self::Retired => false,
        };
    }
}
