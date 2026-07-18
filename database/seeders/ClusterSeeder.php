<?php

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClusterSeeder extends Seeder
{
    public function run(): void
    {
        $clusters = [
            'Cluster A' => 'budi',
            'Cluster B' => 'siti',
            'Cluster C' => 'agus',
        ];

        foreach ($clusters as $name => $username) {
            Cluster::updateOrCreate(
                ['name' => $name],
                ['officer_id' => User::where('username', $username)->firstOrFail()->id],
            );
        }
    }
}
