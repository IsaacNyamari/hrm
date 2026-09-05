<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves which channels a notification should use for a given user, honouring
 * their per-category preferences. In-app (`database`) delivery is always kept;
 * only `mail` can be silenced.
 */
class NotificationChannels
{
    /**
     * @return array<int, string>
     */
    public static function forUser(User $user, NotificationCategory $category): array
    {
        $channels = ['database'];

        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('category', $category->value)
            ->first();

        if ($preference === null || $preference->mailEnabled()) {
            array_unshift($channels, 'mail');
        }

        return $channels;
    }
}
