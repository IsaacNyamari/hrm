<?php

namespace Database\Factories;

use App\Enums\CallStatus;
use App\Models\Call;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
{
    protected $model = Call::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by' => User::factory(),
            'status' => CallStatus::Ringing,
        ];
    }

    public function forTenant(int|Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
        ]);
    }
}
