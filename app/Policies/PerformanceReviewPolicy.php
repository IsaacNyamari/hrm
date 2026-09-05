<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class PerformanceReviewPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        if ($user->tenant_id !== $review->tenant_id) {
            return false;
        }

        return $this->isReviewer($user, $review)
            || $this->isSubject($user, $review)
            || $this->managesSubject($user, $review)
            || $this->permits($user, $review->tenant_id, Permission::PerformanceView, Permission::PerformanceManage);
    }

    /**
     * Fill in / submit the review. The assigned reviewer, the subject's line
     * manager (covers a review left unassigned), or a performance admin.
     */
    public function submit(User $user, PerformanceReview $review): bool
    {
        if ($user->tenant_id !== $review->tenant_id) {
            return false;
        }

        return $this->isReviewer($user, $review)
            || $this->managesSubject($user, $review)
            || $this->permits($user, $review->tenant_id, Permission::PerformanceManage);
    }

    public function acknowledge(User $user, PerformanceReview $review): bool
    {
        return $user->tenant_id === $review->tenant_id && $this->isSubject($user, $review);
    }

    private function isReviewer(User $user, PerformanceReview $review): bool
    {
        return $review->reviewer_id === $user->getKey();
    }

    private function isSubject(User $user, PerformanceReview $review): bool
    {
        return $review->employee->user_id === $user->getKey();
    }

    private function managesSubject(User $user, PerformanceReview $review): bool
    {
        return $review->employee->manager?->user_id === $user->getKey();
    }
}
