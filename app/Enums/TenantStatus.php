<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /**
     * Whether a tenant in this status may use the tenant application
     * (subject to an unexpired trial when Trialing).
     */
    public function grantsAppAccess(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }
}
