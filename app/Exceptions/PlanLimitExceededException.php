<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    public static function for(string $resource): self
    {
        return new self("You have reached your plan limit for {$resource}. Upgrade your plan to add more.");
    }
}
