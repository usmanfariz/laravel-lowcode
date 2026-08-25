<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TABEL CONTOH — sengaja dibuat kaya tipe data untuk menguji generator CRUD
 * (tahap 8): enum, foreign key, boolean, decimal, date, datetime, kolom
 * bernama email/website/logo/file, kolom audit, dan soft delete.
 *
 * Sama seperti 2026_08_25_100001, ini pengecualian atas prinsip "aplikasi
 * tidak membuat tabel bisnis" (docs/RANCANGAN.md §2) dan aman dihapus.
 * Jangan jalankan di produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Kode unik pemasok');
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('demo_categories');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->integer('rating')->nullable();
            $table->enum('status', ['active', 'suspended', 'blacklisted'])->default('active');
            $table->string('logo', 255)->nullable();
            $table->string('contract_file', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('joined_on')->nullable();
            $table->dateTime('last_contacted_at')->nullable();
            $table->string('branch_code', 100)->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_suppliers');
    }
};
