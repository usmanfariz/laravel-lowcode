<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('avatar', 255)->nullable()->after('phone');

            // Nilai pembanding untuk role dengan data_scope = branch.
            // Diisi id cabang / unit / departemen sesuai kebutuhan aplikasi.
            $table->string('scope_value', 100)->nullable()->after('avatar');

            $table->boolean('is_active')->default(true)->after('scope_value');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index('scope_value');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['scope_value']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username', 'phone', 'avatar', 'scope_value',
                'is_active', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
