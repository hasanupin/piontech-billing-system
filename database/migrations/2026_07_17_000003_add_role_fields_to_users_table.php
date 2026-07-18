<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('name');
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->enum('role', ['super_admin', 'admin', 'field_officer'])
                ->default('field_officer')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->foreignId('created_by')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'username',
                'phone',
                'role',
                'is_active',
                'created_by',
                'deleted_at',
            ]);
        });
    }
};
