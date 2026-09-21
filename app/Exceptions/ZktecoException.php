<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A ZKTeco biometric-device business-rule failure. Rendered as HTTP 422.
 */
class ZktecoException extends RuntimeException
{
    public static function alreadyClaimed(): self
    {
        return new self('This device is already registered to another company.');
    }

    public static function notClaimed(): self
    {
        return new self('This device has not been registered to your company yet.');
    }
}
