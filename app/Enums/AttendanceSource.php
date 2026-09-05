<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceSource: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Biometric = 'biometric';
    case Api = 'api';
    case Admin = 'admin';
    case Import = 'import';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
