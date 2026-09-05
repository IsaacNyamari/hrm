<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
