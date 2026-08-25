<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whitelist tabel yang boleh disentuh oleh engine.
     *
     * Ini pengaman utama aplikasi. Nama tabel pada metadata (forms.table_name,
     * reports.base_table, form_fields.data_source) TIDAK BOLEH langsung dipakai
     * di query sebelum dicocokkan ke tabel ini. Tanpa lapisan ini, siapa pun yang
     * bisa menyunting metadata otomatis bisa membaca seluruh isi database.
     */
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('connection', 100)->default('mysql'); // nama koneksi di config/database.php
            $table->string('table_name', 150);
            $table->string('label', 150)->nullable();
            $table->string('primary_key', 100)->default('id');

            $table->boolean('is_readable')->default(true);  // boleh jadi sumber select / report
            $table->boolean('is_writable')->default(false); // boleh jadi target form CRUD

            // Kolom yang tidak boleh ditampilkan atau diambil sama sekali,
            // contoh: ["password","remember_token","api_token"]
            $table->json('blocked_columns')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['connection', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
