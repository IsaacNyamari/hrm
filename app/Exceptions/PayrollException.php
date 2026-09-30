<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A payroll business-rule or configuration failure (bad formula, locked run,
 * missing statutory rule, …). Rendered as HTTP 422.
 */
class PayrollException extends RuntimeException
{
    public static function runLocked(): self
    {
        return new self('This payroll run is finalised and can no longer be changed.');
    }

    public static function periodNotEnded(string $endDate): self
    {
        return new self("This payroll cannot be approved before the period ends on {$endDate}. Approving early freezes pay before leavers, joiners and attendance are known.");
    }

    public static function sameUserCannotApprove(): self
    {
        return new self('A payroll run must be approved by someone other than the person who processed it.');
    }

    /**
     * @param  list<string>  $issues
     */
    public static function reconciliationFailed(array $issues): self
    {
        return new self('The payroll run does not reconcile: '.implode(' ', $issues));
    }

    public static function notProcessed(): self
    {
        return new self('Process the payroll run before this action.');
    }

    public static function alreadyRun(): self
    {
        return new self('This period already has a payroll run.');
    }

    public static function noStatutoryRule(string $type): self
    {
        return new self("No active {$type} rule for this pay date. Add one under Payroll → Tax rules.");
    }

    public static function circularComponents(): self
    {
        return new self('Salary components reference each other in a loop.');
    }
}
