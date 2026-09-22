<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expo_push_token' => 'ExponentPushToken['.Str::random(22).']',
            'platform' => fake()->randomElement(DeviceToken::PLATFORMS),
            'last_seen_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey(), 'tenant_id' => $user->tenant_id]);
    }
}
