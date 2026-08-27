<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kolom detail mana yang ikut dijumlahkan di baris total.
 *
 * `form_details.show_total_row` sudah ada sejak awal dan bisa dicentang admin,
 * tapi tidak pernah dirender — tidak ada cara menyatakan kolom mana yang harus
 * dijumlahkan. Namanya mengikuti `report_columns.show_total` yang lebih dulu ada,
 * supaya satu istilah berarti satu hal di seluruh aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->boolean('show_total')->default(false)->after('is_unique');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('show_total');
        });
    }
};
