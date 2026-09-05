<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeProfileUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly Employee $employee,
        public readonly array $changes,
        public readonly bool $bySelf,
    ) {}
}
