<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'tenant_id' => null,
            'is_platform_admin' => false,
        ];
    }

    /**
     * Platform staff account (no tenant). Assigns the Spatie platform role under
     * the reserved platform team.
     */
    public function platformAdmin(?Role $role = null): static
    {
        $role ??= Role::SuperAdmin;

        return $this->state(fn (array $attributes): array => [
            'tenant_id' => null,
            'is_platform_admin' => true,
        ])->afterCreating(function (User $user) use ($role): void {
            $registrar = app(PermissionRegistrar::class);
            $previous = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId(TenantContext::PLATFORM_TEAM_ID);

            try {
                \Spatie\Permission\Models\Role::findOrCreate($role->value, 'web')
                    ->forceFill(['tenant_id' => TenantContext::PLATFORM_TEAM_ID])->save();
                $user->assignRole($role->value);
            } finally {
                $registrar->setPermissionsTeamId($previous);
            }
        });
    }

    /**
     * Attach the user to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->getKey(),
            'is_platform_admin' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
