<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referal pelanggan — nullable: mayoritas pelanggan tanpa komisi.
 * Penerima dihapus → referal jadi NULL, pelanggannya aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUlid('referral_id')->nullable()->after('package_id')
                ->constrained('commission_recipients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_id');
        });
    }
};
