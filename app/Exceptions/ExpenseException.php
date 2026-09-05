<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An expense-claim business-rule failure. Rendered as HTTP 422.
 */
class ExpenseException extends RuntimeException
{
    public static function notEditable(): self
    {
        return new self('This claim can no longer be edited.');
    }

    public static function noItems(): self
    {
        return new self('Add at least one line item before submitting.');
    }

    public static function receiptRequired(string $category): self
    {
        return new self("A receipt is required for {$category} line items.");
    }

    public static function notPending(): self
    {
        return new self('This claim is not awaiting a decision.');
    }

    public static function notAnApprover(): self
    {
        return new self('You cannot act on this claim at its current stage.');
    }

    public static function notApproved(): self
    {
        return new self('Only an approved claim can be marked reimbursed.');
    }
}
