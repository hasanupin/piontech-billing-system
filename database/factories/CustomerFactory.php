<?php

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'full_name' => fake()->name(),
            'whatsapp_number' => '628'.fake()->unique()->numerify('#########'),
            'cluster_id' => Cluster::factory(),
            'package_id' => Package::factory(),
            'billing_amount' => fake()->randomElement([100_000, 110_000, 115_000]),
            'address' => fake()->streetName(),
            'billing_day' => fake()->numberBetween(1, 28),
            'status' => 'active',
            'registered_at' => fake()->dateTimeBetween('-2 years'),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'suspended_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'terminated',
        ]);
    }
}
