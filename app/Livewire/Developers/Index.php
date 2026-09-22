<?php

declare(strict_types=1);

namespace App\Livewire\Developers;

use App\Actions\Mobile\IssueMobileToken;
use App\Enums\WebhookEvent;
use App\Http\Controllers\ApiTokenController;
use App\Livewire\Concerns\BindsTenant;
use App\Models\DeviceToken;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

class Index extends Component
{
    use BindsTenant;

    public string $tokenName = '';

    /** @var array<int, string> */
    public array $tokenAbilities = ['employees:read'];

    public ?string $newPlainToken = null;

    public string $webhookName = '';

    public string $webhookUrl = '';

    public ?string $notice = null;

    public function createToken(): void
    {
        $this->reset(['notice', 'newPlainToken']);
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->validate(['tokenName' => ['required', 'string', 'max:80']]);

        $abilities = array_values(array_intersect($this->tokenAbilities, ApiTokenController::ABILITIES)) ?: ['employees:read'];
        $token = $user->createToken($this->tokenName, $abilities);

        $this->newPlainToken = $token->plainTextToken;
        $this->tokenName = '';
    }

    public function revokeToken(int $id): void
    {
        Auth::user()?->tokens()->whereKey($id)->delete();
        $this->notice = 'Token revoked.';
    }

    /**
     * Sign a mobile device out: its token stops working and it gets no more
     * push notifications.
     */
    public function signOutDevice(int $id): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        DeviceToken::query()->where('user_id', $user->getKey())->where('personal_access_token_id', $id)->delete();
        $user->tokens()->whereKey($id)->delete();
        $this->notice = 'Device signed out.';
    }

    public function createWebhook(): void
    {
        $this->reset('notice');
        abort_unless(Auth::user()?->can('webhooks.manage'), 403);

        $this->validate([
            'webhookName' => ['required', 'string', 'max:120'],
            'webhookUrl' => ['required', 'url', 'max:500'],
        ]);

        WebhookEndpoint::query()->create([
            'name' => $this->webhookName,
            'url' => $this->webhookUrl,
            'secret' => Str::random(40),
            'events' => ['*'],
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['webhookName', 'webhookUrl']);
        $this->notice = 'Webhook endpoint added.';
    }

    public function deleteWebhook(string $uuid): void
    {
        abort_unless(Auth::user()?->can('webhooks.manage'), 403);
        WebhookEndpoint::query()->where('uuid', $uuid)->delete();
        $this->notice = 'Webhook endpoint removed.';
    }

    public function render(): View
    {
        $user = Auth::user();
        [$mobileSessions, $tokens] = ($user !== null ? $user->tokens()->latest('id')->get() : collect())
            ->partition(fn (PersonalAccessToken $token): bool => in_array(IssueMobileToken::ABILITY, (array) $token->abilities, true));

        return view('livewire.developers.index', [
            'tokens' => $tokens,
            'mobileSessions' => $mobileSessions,
            'abilities' => ApiTokenController::ABILITIES,
            'canManageWebhooks' => $user?->can('webhooks.manage') === true,
            'webhooks' => ($user?->can('webhooks.manage') === true)
                ? WebhookEndpoint::query()->latest('id')->get()
                : collect(),
            'events' => WebhookEvent::subscribable(),
        ]);
    }
}
