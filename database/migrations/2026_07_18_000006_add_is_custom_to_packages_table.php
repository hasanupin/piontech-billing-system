<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Paket custom: harga diisi per pelanggan, tidak perlu harga default.
            $table->boolean('is_custom')->default(false)->after('is_active');
            $table->decimal('default_price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_custom');
            $table->decimal('default_price', 12, 2)->nullable(false)->change();
        });
    }
};
