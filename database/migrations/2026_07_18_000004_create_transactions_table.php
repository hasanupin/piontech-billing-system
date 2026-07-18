<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')
                ->constrained('customers')->cascadeOnDelete();
            // NULL saat payment_method=transfer (bayar langsung ke rekening, tanpa petugas).
            $table->foreignId('officer_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->date('period'); // YYYY-MM-01
            // Input manual — pre-fill dari default_price paket tapi editable.
            $table->decimal('billed_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->enum('status', ['paid', 'partial', 'unpaid'])->default('paid');
            $table->enum('payment_method', ['cash', 'transfer'])->default('cash');
            $table->foreignId('recorded_by')->constrained('users');
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            // Satu customer hanya boleh 1 transaksi per periode bulan.
            $table->unique(['customer_id', 'period']);
            $table->index(['officer_id', 'period', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
