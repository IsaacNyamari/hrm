<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule failure while recording attendance (already clocked in, not
 * clocked in, outside geofence, …). Rendered as HTTP 422.
 */
class AttendanceException extends RuntimeException
{
    public static function alreadyClockedIn(): self
    {
        return new self('You are already clocked in for today.');
    }

    public static function notClockedIn(): self
    {
        return new self('You are not clocked in.');
    }

    public static function alreadyClockedOut(): self
    {
        return new self('You have already clocked out for today.');
    }

    public static function notOnBreak(): self
    {
        return new self('There is no break in progress.');
    }

    public static function alreadyOnBreak(): self
    {
        return new self('A break is already in progress.');
    }

    public static function coordinatesRequired(): self
    {
        return new self('Location is required to clock in or out here.');
    }

    public static function outsideGeofence(): self
    {
        return new self('You are outside the allowed clock-in area.');
    }

    public static function noEmployeeRecord(): self
    {
        return new self('Your account is not linked to an employee record.');
    }
}
