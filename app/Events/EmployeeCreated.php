<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeCreated
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
