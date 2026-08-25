<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TABEL CONTOH — BUKAN BAGIAN DARI ENGINE.
 *
 * Dibuat hanya agar Dynamic CRUD bisa dicoba di lingkungan pengembangan.
 * Aplikasi ini pada dasarnya tidak membuat tabel bisnis (lihat docs/RANCANGAN.md §2);
 * berkas ini pengecualian yang disengaja dan aman dihapus:
 *
 *   php artisan migrate:rollback --step=1
 *   rm database/migrations/2026_08_25_100001_create_demo_business_tables.php
 *
 * Jangan jalankan migrasi ini di produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('demo_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->foreignId('category_id')->nullable()->constrained('demo_categories');
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('description')->nullable();
            $table->string('photo', 255)->nullable();

            // Dipakai roles.data_scope = 'branch' lewat forms.scope_column.
            $table->string('branch_code', 100)->nullable()->index();

            $table->boolean('is_active')->default(true);

            // Kolom audit yang diisi otomatis engine bila forms.use_audit_column aktif.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_products');
        Schema::dropIfExists('demo_categories');
    }
};
