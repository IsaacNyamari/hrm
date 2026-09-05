<?php

declare(strict_types=1);

namespace App\Enums;

enum PerformanceCycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case InReview = 'in_review';
    case Closed = 'closed';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Goals and KPIs may be created / edited while the cycle is running.
     */
    public function acceptsGoals(): bool
    {
        return $this === self::Draft || $this === self::Active;
    }

    /**
     * Reviewers may fill in and submit reviews.
     */
    public function acceptsReviews(): bool
    {
        return $this === self::Active || $this === self::InReview;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }
}
