<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomElement([100_000, 110_000, 115_000]);

        return [
            'customer_id' => Customer::factory(),
            'officer_id' => User::factory()->fieldOfficer(),
            'period' => now()->startOfMonth(),
            'billed_amount' => $amount,
            'paid_amount' => $amount,
            'status' => 'paid',
            'payment_method' => 'cash',
            'recorded_by' => User::factory()->admin(),
            'paid_at' => now(),
        ];
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'transfer',
            'officer_id' => null,
        ]);
    }
}
