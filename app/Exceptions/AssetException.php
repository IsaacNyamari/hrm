<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An asset-register business-rule failure. Rendered as HTTP 422.
 */
class AssetException extends RuntimeException
{
    public static function notAvailable(): self
    {
        return new self('This asset is not available to assign.');
    }

    public static function alreadyAssigned(): self
    {
        return new self('This asset already has an active assignment.');
    }

    public static function notActive(): self
    {
        return new self('This assignment is not active.');
    }

    public static function hasActiveAssignment(): self
    {
        return new self('Return the asset before retiring it.');
    }
}
