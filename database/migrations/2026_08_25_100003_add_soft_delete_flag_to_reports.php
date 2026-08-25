<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `forms` punya use_soft_delete, `reports` tidak — akibatnya report menampilkan
 * baris yang sudah dihapus lewat form. Asimetri ini sudah ditandai di
 * docs/RANCANGAN.md §12 sebagai celah, dan inilah perbaikannya.
 *
 * Default sengaja TRUE: report yang diam-diam memuat baris terhapus adalah
 * kesalahan data yang sulit disadari, sedangkan flag ini tidak berpengaruh
 * pada tabel yang memang tidak punya kolom deleted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('use_soft_delete')->default(true)->after('base_alias');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('use_soft_delete');
        });
    }
};
