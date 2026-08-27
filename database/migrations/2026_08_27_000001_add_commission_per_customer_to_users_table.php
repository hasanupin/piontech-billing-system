<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komisi petugas lapangan: nominal tetap per pelanggan yang tertagih, tersimpan
 * per user supaya mengubah default tidak menyentuh petugas lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('commission_per_customer', 12, 2)->default(4000)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('commission_per_customer');
        });
    }
};
