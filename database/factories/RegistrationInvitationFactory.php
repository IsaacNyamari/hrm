<?php

namespace Database\Factories;

use App\Models\RegistrationInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegistrationInvitation>
 */
class RegistrationInvitationFactory extends Factory
{
    protected $model = RegistrationInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'company' => fake()->company(),
            'token_hash' => RegistrationInvitation::hashToken(Str::random(64)),
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * Use a known plain token (the link's secret) for this invitation.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['token_hash' => RegistrationInvitation::hashToken($token)]);
    }
}
