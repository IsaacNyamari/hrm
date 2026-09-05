<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicantSource: string
{
    case CareersSite = 'careers_site';
    case Referral = 'referral';
    case Agency = 'agency';
    case LinkedIn = 'linkedin';
    case JobBoard = 'job_board';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }
}
