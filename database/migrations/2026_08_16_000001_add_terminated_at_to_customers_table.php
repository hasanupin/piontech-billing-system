<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal berhenti berlangganan — pasangan suspended_at. Tanpa kolom ini
 * churn per bulan di Laporan Pelanggan tidak bisa dihitung (status terminated
 * tidak menyimpan kapan terjadinya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('terminated_at')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('terminated_at');
        });
    }
};
