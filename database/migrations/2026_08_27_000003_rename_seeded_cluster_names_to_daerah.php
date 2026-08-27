<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Istilah "Cluster" diganti "Daerah" di seluruh UI. Nama daerah bawaan seeder
 * ikut disesuaikan supaya menu Daerah tidak berisi "Cluster A".
 *
 * Sengaja HANYA menyentuh tiga nama bawaan seeder — nama yang sudah disunting
 * klien adalah datanya sendiri dan tidak boleh ditimpa.
 */
return new class extends Migration
{
    private const RENAMES = [
        'Cluster A' => 'Daerah A',
        'Cluster B' => 'Daerah B',
        'Cluster C' => 'Daerah C',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('clusters')->where('name', $from)->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('clusters')->where('name', $to)->update(['name' => $from]);
        }
    }
};
