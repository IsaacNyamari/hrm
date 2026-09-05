<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

class ApplicationRejected
{
    use Dispatchable;

    public function __construct(public readonly Application $application) {}
}
