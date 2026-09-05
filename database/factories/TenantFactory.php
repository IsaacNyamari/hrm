<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'legal_name' => $name.' Ltd',
            'registration_number' => 'C.'.fake()->numberBetween(10000, 99999),
            'tax_number' => 'P'.fake()->numerify('#########').'A',
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+2547'.fake()->numerify('########'),
            'website' => 'https://'.fake()->domainName(),
            'city' => fake()->city(),
            'country' => 'KE',
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'fiscal_year_start' => 1,
            'status' => TenantStatus::Active,
            'trial_ends_at' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Suspended,
        ]);
    }

    public function withSlug(string $slug): static
    {
        return $this->state(fn (): array => ['slug' => $slug]);
    }
}
