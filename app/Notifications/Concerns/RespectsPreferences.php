<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Support\NotificationChannels;

/**
 * A notification that routes through a user's per-category channel preferences.
 * Implementers declare {@see preferenceCategory()}; non-user notifiables always
 * get mail + database.
 */
trait RespectsPreferences
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User) {
            return NotificationChannels::forUser($notifiable, $this->preferenceCategory());
        }

        return ['mail', 'database'];
    }

    abstract public function preferenceCategory(): NotificationCategory;
}
