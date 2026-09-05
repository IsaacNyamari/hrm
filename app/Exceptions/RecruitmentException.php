<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A recruitment / ATS business-rule failure. Rendered as HTTP 422.
 */
class RecruitmentException extends RuntimeException
{
    public static function vacancyNotOpen(): self
    {
        return new self('This vacancy is not accepting applications.');
    }

    public static function duplicateApplication(): self
    {
        return new self('This candidate has already applied to this vacancy.');
    }

    public static function unknownStage(string $stage): self
    {
        return new self("\"{$stage}\" is not a stage in this vacancy's pipeline.");
    }

    public static function applicationClosed(): self
    {
        return new self('This application is no longer active.');
    }

    public static function interviewNotScheduled(): self
    {
        return new self('Feedback can only be recorded for a scheduled interview.');
    }

    public static function offerNotPending(): self
    {
        return new self('This offer can no longer be changed.');
    }

    public static function offerNotAccepted(): self
    {
        return new self('Only an accepted offer can be converted to an employee.');
    }

    public static function alreadyConverted(): self
    {
        return new self('This offer has already been converted to an employee.');
    }
}
