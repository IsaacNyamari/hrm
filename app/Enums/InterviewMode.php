<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewMode: string
{
    case Onsite = 'onsite';
    case Phone = 'phone';
    case Video = 'video';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
