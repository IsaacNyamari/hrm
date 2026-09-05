<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PayrollRun;
use Illuminate\Foundation\Events\Dispatchable;

class PayrollApproved
{
    use Dispatchable;

    public function __construct(public readonly PayrollRun $run) {}
}
