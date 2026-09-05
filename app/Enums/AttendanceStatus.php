<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case EarlyDeparture = 'early_departure';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case WeekOff = 'week_off';
    case RemoteWork = 'remote_work';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isWorked(): bool
    {
        return match ($this) {
            self::Present, self::Late, self::EarlyDeparture, self::HalfDay, self::RemoteWork => true,
            default => false,
        };
    }
}
