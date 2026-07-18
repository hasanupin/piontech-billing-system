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
        // Paket berharga saja untuk seed — billing_amount ikut harga paketnya.
        $packages = Package::where('is_custom', false)->get(['id', 'default_price']);

        // 50 pelanggan dummy: ~45 active + 5 suspended, tersebar rata di 3 cluster.
        foreach (range(0, 49) as $i) {
            $package = $packages->random();

            Customer::factory()
                ->when($i < 5, fn ($factory) => $factory->suspended())
                ->create([
                    'cluster_id' => $clusters[$i % $clusters->count()],
                    'package_id' => $package->id,
                    'billing_amount' => $package->default_price,
                ]);
        }
    }
}
