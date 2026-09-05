<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordLoginActivity
{
    public function __construct(private readonly Request $request) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        LoginActivity::create([
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'tenant_id' => $user instanceof User ? $user->tenant_id : null,
            'email' => $user->getAttribute('email'),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'successful' => true,
            'created_at' => now(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        LoginActivity::create([
            'user_id' => $event->user instanceof User ? $event->user->getKey() : null,
            'email' => $event->credentials['email'] ?? null,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'successful' => false,
            'reason' => 'invalid_credentials',
            'created_at' => now(),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        LoginActivity::create([
            'email' => $event->request->input('email'),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'successful' => false,
            'reason' => 'lockout',
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
        ];
    }
}
