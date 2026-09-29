<?php

namespace Database\Factories;

use App\Enums\CallParticipantStatus;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallParticipant>
 */
class CallParticipantFactory extends Factory
{
    protected $model = CallParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'call_id' => Call::factory(),
            'user_id' => User::factory(),
            'status' => CallParticipantStatus::Invited,
            'invited_at' => now(),
        ];
    }

    public function forCall(Call $call): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $call->tenant_id,
            'call_id' => $call->id,
        ]);
    }

    public function joined(): static
    {
        return $this->state(fn (): array => [
            'status' => CallParticipantStatus::Joined,
            'joined_at' => now(),
        ]);
    }
}
