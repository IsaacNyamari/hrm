<?php

declare(strict_types=1);

namespace App\Enums;

enum CallParticipantStatus: string
{
    case Invited = 'invited';
    case Joined = 'joined';
    case Declined = 'declined';
    case Left = 'left';
    case Missed = 'missed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
