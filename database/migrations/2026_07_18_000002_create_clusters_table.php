<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            // PIC cluster — ganti petugas cukup update kolom ini, semua customer ikut.
            $table->foreignId('officer_id')
                ->constrained('users')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clusters');
    }
};
