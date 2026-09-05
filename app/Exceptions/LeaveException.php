<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A leave business-rule failure. Rendered as HTTP 422.
 */
class LeaveException extends RuntimeException
{
    public static function invalidRange(): self
    {
        return new self('The end date must be on or after the start date.');
    }

    public static function insufficientNotice(int $days): self
    {
        return new self("This leave type requires at least {$days} day(s) notice.");
    }

    public static function zeroWorkingDays(): self
    {
        return new self('The selected dates contain no working days.');
    }

    public static function exceedsConsecutive(int $max): self
    {
        return new self("This leave type allows at most {$max} consecutive day(s).");
    }

    public static function attachmentRequired(): self
    {
        return new self('This leave type requires a supporting document.');
    }

    public static function genderRestricted(): self
    {
        return new self('This leave type is not available for this employee.');
    }

    public static function overlapping(): self
    {
        return new self('This overlaps an existing leave request.');
    }

    public static function insufficientBalance(float $remaining, float $requested): self
    {
        return new self("Insufficient balance: {$remaining} day(s) available, {$requested} requested.");
    }

    public static function notPending(): self
    {
        return new self('This request is no longer pending.');
    }

    public static function notAnApprover(): self
    {
        return new self('You cannot act on this request at its current stage.');
    }

    public static function cannotCancel(): self
    {
        return new self('This request can no longer be cancelled.');
    }
}
