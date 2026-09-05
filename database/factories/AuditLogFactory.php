<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'event' => fake()->randomElement(['employee.updated', 'leave.approved', 'auth.login', 'expense.approved']),
            'actor_label' => fake()->name().' <'.fake()->safeEmail().'>',
            'ip_address' => fake()->ipv4(),
            'via_impersonation' => false,
            'created_at' => now(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->getKey()]);
    }
}
