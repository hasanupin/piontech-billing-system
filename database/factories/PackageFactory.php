<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomElement([100_000, 110_000, 115_000, 150_000]);

        return [
            'name' => 'Package '.($price / 1000),
            'default_price' => $price,
            'speed_mbps' => fake()->randomElement([10, 20, 30, 50]),
            'is_active' => true,
        ];
    }
}
