<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\HrRequest;
use Illuminate\Foundation\Events\Dispatchable;

class HrRequestSubmitted
{
    use Dispatchable;

    public function __construct(public readonly HrRequest $request) {}
}
