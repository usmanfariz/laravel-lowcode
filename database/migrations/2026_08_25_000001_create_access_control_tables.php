<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();

            // Cakupan data yang boleh dilihat pemegang role ini.
            // all    = seluruh baris
            // own    = hanya baris yang dibuat sendiri (created_by)
            // branch = hanya baris dengan scope_value sama dengan milik user
            // custom = ditangani oleh logika khusus di aplikasi
            $table->enum('data_scope', ['all', 'own', 'branch', 'custom'])->default('all');

            $table->boolean('is_system')->default(false); // role bawaan, tidak boleh dihapus
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 150)->unique();  // contoh: customer.create, report.sales.export
            $table->string('name', 150);
            $table->string('group_name', 100)->nullable(); // pengelompokan di halaman role
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('group_name');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
