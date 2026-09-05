<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Interview;
use Illuminate\Foundation\Events\Dispatchable;

class InterviewScheduled
{
    use Dispatchable;

    public function __construct(public readonly Interview $interview) {}
}
