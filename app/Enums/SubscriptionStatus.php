<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /**
     * Statuses that let the tenant use the application.
     *
     * @return array<int, self>
     */
    public static function operational(): array
    {
        return [self::Trialing, self::Active];
    }

    public function grantsAppAccess(): bool
    {
        return in_array($this, self::operational(), true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
