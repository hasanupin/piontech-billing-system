<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cluster opsional saat import Excel — admin bisa assign belakangan via UI.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUlid('cluster_id')->nullable()->change();
            $table->foreign('cluster_id')->references('id')->on('clusters')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['cluster_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUlid('cluster_id')->nullable(false)->change();
            $table->foreign('cluster_id')->references('id')->on('clusters')->restrictOnDelete();
        });
    }
};
