<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan agar halaman Pengaturan bisa digambar dari metadata.
 *
 * `value_type` menentukan cara nilai disimpan dan di-cast; itu saja tidak cukup
 * untuk memilih bentuk isian — teks satu baris, area teks, dan pilihan sama-sama
 * bertipe string. `input_type` mengurus tampilannya, `options` mengisi pilihannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('input_type', 30)->nullable()->after('value_type');
            $table->json('options')->nullable()->after('input_type');
            $table->unsignedSmallInteger('order_no')->default(0)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['input_type', 'options', 'order_no']);
        });
    }
};
