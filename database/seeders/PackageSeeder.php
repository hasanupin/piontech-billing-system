<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Package 100', 'default_price' => 100_000, 'speed_mbps' => 10],
            ['name' => 'Package 110', 'default_price' => 110_000, 'speed_mbps' => 20],
            ['name' => 'Package 115', 'default_price' => 115_000, 'speed_mbps' => 30],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(['name' => $package['name']], $package);
        }
    }
}
