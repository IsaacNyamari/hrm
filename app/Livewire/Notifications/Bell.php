<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The header bell: unread count that updates live, with a toast for each new
 * notification pushed on the user's private channel.
 */
class Bell extends Component
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = auth()->id();

        return $id === null ? [] : ["echo-private:App.Models.User.{$id},.user.notified" => 'notified'];
    }

    /**
     * @param  array{title?: string, detail?: string}  $payload
     */
    public function notified(array $payload = []): void
    {
        Flux::toast(
            text: (string) ($payload['detail'] ?? ''),
            heading: (string) ($payload['title'] ?? __('New notification')),
        );
    }

    public function render(): View
    {
        return view('livewire.notifications.bell', [
            'unread' => auth()->user()?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
