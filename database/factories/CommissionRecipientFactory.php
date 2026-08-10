<?php

namespace Database\Factories;

use App\Enums\RecipientType;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRecipient>
 */
class CommissionRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RecipientType::External,
            'name' => fake()->name(),
            'address' => fake()->streetName(),
            'whatsapp_number' => '628'.fake()->unique()->numerify('#########'),
            'commission_percent' => 4,
            'is_active' => true,
        ];
    }

    /** Tipe Pelanggan: kontak mirror ke customers, kolomnya sendiri dikosongkan. */
    public function customerType(?Customer $customer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RecipientType::Customer,
            'customer_id' => $customer?->getKey() ?? Customer::factory(),
            'name' => null,
            'address' => null,
            'whatsapp_number' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
