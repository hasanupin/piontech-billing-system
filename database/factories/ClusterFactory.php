<?php

namespace Database\Factories;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cluster>
 */
class ClusterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Daerah '.fake()->unique()->lexify('??'),
            'officer_id' => User::factory()->fieldOfficer(),
            'is_active' => true,
        ];
    }
}
