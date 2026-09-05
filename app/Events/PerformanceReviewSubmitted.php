<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PerformanceReview;
use Illuminate\Foundation\Events\Dispatchable;

class PerformanceReviewSubmitted
{
    use Dispatchable;

    public function __construct(public readonly PerformanceReview $review) {}
}
