<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Nominal tagihan per pelanggan — prefill dari paket, bisa diubah (jadi custom).
            $table->decimal('billing_amount', 12, 2)->nullable()->after('package_id');
        });

        // Backfill data lama dari harga paket.
        DB::statement('UPDATE customers SET billing_amount = (SELECT default_price FROM packages WHERE packages.id = customers.package_id) WHERE billing_amount IS NULL');
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('billing_amount');
        });
    }
};
