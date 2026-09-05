<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Rescinded = 'rescinded';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isPending(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }
}
