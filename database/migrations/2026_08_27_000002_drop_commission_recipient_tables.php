<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komisi referal diganti komisi petugas (users.commission_per_customer):
 * penerima komisi & kolom referal pelanggan tidak dipakai lagi.
 *
 * Tabel `settings` ikut dibuang — satu-satunya isinya adalah
 * default_commission_percent yang mati bersama model komisi lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FK dulu, tabelnya belakangan.
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('referral_id');
        });

        Schema::dropIfExists('commission_recipients');
        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        throw new RuntimeException('Komisi referal sudah dihapus permanen; pulihkan lewat backup database.');
    }
};
