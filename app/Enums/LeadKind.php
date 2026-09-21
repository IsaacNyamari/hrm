<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadKind: string
{
    case Quote = 'quote';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Quote request',
            self::Contact => 'Contact message',
        };
    }
}
