<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aktivitas per user: input data, perubahan status, input pembayaran,
 * akses menu, login/logout, dan export. Append-only (tanpa updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('event', 20);

            // subject_id sengaja string, BUKAN ulidMorphs(): subjeknya bisa ULID
            // (tabel domain), bigint (users), atau null (halaman/login/export).
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            // Snapshot nama, supaya log tetap terbaca setelah record dihapus.
            $table->string('subject_label')->nullable();

            // BUKAN 'changes': Eloquent punya properti internal $changes, sehingga
            // $this->changes di dalam model membaca dirty-tracking, bukan kolom ini.
            $table->json('changed_values')->nullable();
            $table->string('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
