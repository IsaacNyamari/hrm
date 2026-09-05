<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A performance-management business-rule failure. Rendered as HTTP 422.
 */
class PerformanceException extends RuntimeException
{
    public static function cycleNotAcceptingGoals(): self
    {
        return new self('Goals can only be added or changed while the cycle is a draft or active.');
    }

    public static function cycleNotInReview(): self
    {
        return new self('This cycle is not accepting reviews right now.');
    }

    public static function cycleAlreadyClosed(): self
    {
        return new self('This performance cycle is already closed.');
    }

    public static function reviewAlreadySubmitted(): self
    {
        return new self('This review has already been submitted.');
    }

    public static function notTheReviewer(): self
    {
        return new self('You are not the assigned reviewer for this review.');
    }

    public static function notTheSubject(): self
    {
        return new self('Only the employee under review can acknowledge it.');
    }

    public static function reviewNotSubmitted(): self
    {
        return new self('This review cannot be acknowledged until it has been submitted.');
    }

    public static function ratingOutOfRange(int $min, int $max): self
    {
        return new self("Each rating must be between {$min} and {$max}.");
    }

    public static function noRatings(): self
    {
        return new self('Provide at least one competency rating before submitting.');
    }
}
