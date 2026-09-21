<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A task business-rule failure. Rendered as HTTP 422.
 */
class TaskException extends RuntimeException
{
    public static function alreadyFinished(): self
    {
        return new self('This task has already been completed or cancelled.');
    }

    public static function notPending(): self
    {
        return new self('Only a pending task can be started.');
    }

    public static function notAssignee(): self
    {
        return new self('You can only update the status of tasks assigned to you.');
    }
}
