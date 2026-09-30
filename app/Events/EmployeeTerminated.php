<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An employee moved from currently employed to terminated, resigned or retired.
 */
class EmployeeTerminated
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
