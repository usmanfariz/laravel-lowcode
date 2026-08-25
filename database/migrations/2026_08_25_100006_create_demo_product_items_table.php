<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TABEL CONTOH — baris detail untuk demo_products.
 *
 * Dipakai menguji master-detail: satu produk punya banyak varian. Sama seperti
 * migrasi demo lainnya, ini pengecualian atas prinsip "aplikasi tidak membuat
 * tabel bisnis" (docs/RANCANGAN.md §2) dan aman dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_product_items')) {
            return; // sudah dibuat manual saat pengembangan
        }

        Schema::create('demo_product_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('sku', 50);
            $table->string('variant', 100)->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('price', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_product_items');
    }
};
