<?php

namespace Database\Factories;

use App\Models\OfficerDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficerDeposit>
 */
class OfficerDepositFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'officer_id' => User::factory()->fieldOfficer(),
            'period' => now()->startOfMonth(),
            'amount' => fake()->randomElement([100_000, 200_000, 500_000]),
            'received_by' => User::factory()->admin(),
            'deposited_at' => now(),
        ];
    }
}
