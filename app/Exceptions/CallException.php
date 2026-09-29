<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A call business-rule failure. Rendered as HTTP 422.
 */
class CallException extends RuntimeException
{
    public static function notAParticipant(): self
    {
        return new self('You are not part of this call.');
    }

    public static function alreadyEnded(): self
    {
        return new self('This call has already ended.');
    }

    public static function noInvitees(): self
    {
        return new self('Choose at least one online colleague to call.');
    }
}
