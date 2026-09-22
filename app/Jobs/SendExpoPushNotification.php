<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DeviceToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one push notification to every mobile device a user has registered,
 * through Expo's push API. Devices Expo reports as no longer registered (app
 * uninstalled, token rotated) are pruned.
 */
class SendExpoPushNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var int */
    public $tries = 3;

    /** Expo accepts at most 100 messages per request. */
    private const CHUNK = 100;

    /**
     * @param  array<string, mixed>  $data  Deep-link payload the app reads when the notification is tapped.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $tokens = DeviceToken::withoutTenantScope()
            ->where('user_id', $this->userId)
            ->pluck('expo_push_token')
            ->all();

        foreach (array_chunk($tokens, self::CHUNK) as $chunk) {
            $this->send($chunk);
        }
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function send(array $tokens): void
    {
        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $this->title,
            'body' => $this->body,
            'data' => (object) $this->data,
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
        ], $tokens);

        $request = Http::acceptJson()->asJson()->timeout(15);
        $accessToken = config('services.expo.access_token');

        if (is_string($accessToken) && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        $response = $request->post((string) config('services.expo.push_url'), $messages)->throw();

        $tickets = (array) $response->json('data', []);
        $invalid = [];

        foreach ($tickets as $index => $ticket) {
            if (($ticket['status'] ?? null) === 'error'
                && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered'
                && isset($tokens[$index])) {
                $invalid[] = $tokens[$index];
            }
        }

        if ($invalid !== []) {
            DeviceToken::withoutTenantScope()->whereIn('expo_push_token', $invalid)->delete();
        }
    }
}
