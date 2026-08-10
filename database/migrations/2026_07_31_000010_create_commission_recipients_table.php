<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penerima komisi atas referal pelanggan. Tipe "customer" mirror ke tabel
 * customers — kolom name/address/whatsapp_number sengaja NULL di baris itu,
 * datanya dibaca hidup lewat relasi (lihat CommissionRecipient::display*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->enum('type', ['customer', 'external'])->default('external');
            $table->foreignUlid('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('address')->nullable();
            // String, samakan dengan customers — nomor WA bukan bilangan.
            $table->string('whatsapp_number', 20)->nullable();
            $table->decimal('commission_percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_recipients');
    }
};
