<?php

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::count() > 0) {
            return;
        }

        $clusters = Cluster::pluck('id');
        $packages = Package::pluck('id');

        // 50 pelanggan dummy: ~45 active + 5 suspended, tersebar rata di 3 cluster.
        foreach (range(0, 49) as $i) {
            Customer::factory()
                ->when($i < 5, fn ($factory) => $factory->suspended())
                ->create([
                    'cluster_id' => $clusters[$i % $clusters->count()],
                    'package_id' => $packages->random(),
                ]);
        }
    }
}
