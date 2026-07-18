<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->string('full_name')->nullable();
            // String, bukan integer — fix nomor WA scientific notation dari Excel lama.
            $table->string('whatsapp_number', 20)->nullable();
            $table->foreignUlid('cluster_id')
                ->constrained('clusters')->restrictOnDelete();
            // Nullable — paket hanya referensi harga.
            $table->foreignUlid('package_id')->nullable()
                ->constrained('packages')->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('maps_url')->nullable();
            $table->text('house_photo_url')->nullable();
            // 1-31, tanggal tagihan beda per pelanggan.
            $table->tinyInteger('billing_day');
            // suspended (isolir) tetap ditagih; terminated (off) tidak ditagih sama sekali.
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->date('suspended_at')->nullable();
            $table->date('registered_at');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'billing_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
