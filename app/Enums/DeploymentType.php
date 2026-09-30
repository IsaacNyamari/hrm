<?php

declare(strict_types=1);

namespace App\Enums;

enum DeploymentType: string
{
    case Pull = 'pull';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::Pull => 'Pull latest changes',
            self::Rollback => 'Roll back',
        };
    }
}
