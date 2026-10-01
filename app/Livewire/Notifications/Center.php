<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\NotificationCategory;
use App\Livewire\Concerns\BindsTenant;
use App\Models\NotificationPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class Center extends Component
{
    use BindsTenant;

    public bool $unreadOnly = false;

    public function markRead(string $id): void
    {
        Auth::user()?->notifications()->whereKey($id)->get()->each->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    public function delete(string $id): void
    {
        Auth::user()?->notifications()->whereKey($id)->delete();
    }

    public function deleteAll(): void
    {
        Auth::user()?->notifications()->delete();
    }

    public function toggleMail(string $category): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $pref = NotificationPreference::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'category' => $category,
        ]);
        $current = $pref->exists ? $pref->mailEnabled() : true;
        $pref->channels = ['mail' => ! $current];
        $pref->save();
    }

    public function render(): View
    {
        $user = Auth::user();

        $notifications = $user !== null
            ? $user->notifications()
                ->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))
                ->limit(50)->get()
            : collect();

        $prefs = $user !== null
            ? NotificationPreference::query()->where('user_id', $user->getKey())->get()->keyBy('category')
            : collect();

        return view('livewire.notifications.center', [
            'notifications' => $notifications,
            'unread' => $user?->unreadNotifications()->count() ?? 0,
            'categories' => NotificationCategory::userConfigurable(),
            'prefs' => $prefs,
            'headline' => fn (array $data): string => Str::headline((string) ($data['type'] ?? 'Notification')),
        ]);
    }
}
