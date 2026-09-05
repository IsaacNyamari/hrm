<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewerType: string
{
    case SelfReview = 'self';
    case Manager = 'manager';
    case Peer = 'peer';
    case Upward = 'upward';

    public function label(): string
    {
        return match ($this) {
            self::SelfReview => 'Self review',
            self::Manager => 'Manager review',
            self::Peer => 'Peer review',
            self::Upward => 'Upward review',
        };
    }
}
