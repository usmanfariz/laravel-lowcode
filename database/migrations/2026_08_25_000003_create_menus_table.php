<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('icon', 100)->nullable();      // contoh: fas fa-users

            // Salah satu dari tiga ini yang dipakai untuk menentukan tujuan menu.
            $table->enum('link_type', ['route', 'url', 'form', 'report', 'header'])->default('route');
            $table->string('target_value', 255)->nullable(); // nama route / url / forms.code / reports.code

            $table->string('permission_code', 150)->nullable(); // menu disembunyikan bila user tak punya izin
            $table->boolean('open_new_tab')->default(false);
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'order_no']);
            $table->index('permission_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
