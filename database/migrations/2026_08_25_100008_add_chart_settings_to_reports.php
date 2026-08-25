<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `reports.type` sudah punya nilai 'chart' sejak awal, tapi belum ada
 * penyimpanan untuk bentuk grafiknya. Tanpa ini, satu-satunya pilihan adalah
 * menebak, dan tebakan yang sama tidak cocok untuk semua data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('chart_type', ['bar', 'horizontal_bar', 'line', 'area', 'pie', 'doughnut'])
                ->default('bar')
                ->after('type');

            // Grafik dengan ratusan batang tidak terbaca; sisanya tetap ada di
            // tabel di bawahnya.
            $table->unsignedSmallInteger('chart_limit')->default(30)->after('chart_type');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['chart_type', 'chart_limit']);
        });
    }
};
