<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `report_filters.default_value` semula varchar(255) — hanya cukup untuk satu
 * nilai skalar. Operator `between` butuh dua nilai dan `in` / `not_in` butuh
 * N nilai, jadi kolomnya dijadikan JSON.
 *
 * Bentuk yang disimpan selalu larik, walau operatornya bernilai tunggal:
 *   =        -> ["published"]
 *   between  -> ["2026-01-01", "2026-12-31"]
 *   in       -> ["draft", "published"]
 *   is_null  -> []
 *
 * Pemisah koma sengaja tidak dipakai karena nilai teks bisa mengandung koma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_filters', function (Blueprint $table) {
            $table->json('default_values')->nullable()->after('default_value');
        });

        // Nilai lama dipindahkan sebagai larik satu elemen.
        DB::table('report_filters')
            ->whereNotNull('default_value')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('report_filters')
                    ->where('id', $row->id)
                    ->update(['default_values' => json_encode([$row->default_value])]);
            });

        Schema::table('report_filters', function (Blueprint $table) {
            $table->dropColumn('default_value');
        });
    }

    public function down(): void
    {
        Schema::table('report_filters', function (Blueprint $table) {
            $table->string('default_value', 255)->nullable()->after('label_field');
        });

        DB::table('report_filters')
            ->whereNotNull('default_values')
            ->orderBy('id')
            ->each(function ($row) {
                $values = json_decode($row->default_values, true) ?: [];
                DB::table('report_filters')
                    ->where('id', $row->id)
                    ->update(['default_value' => $values[0] ?? null]);
            });

        Schema::table('report_filters', function (Blueprint $table) {
            $table->dropColumn('default_values');
        });
    }
};
