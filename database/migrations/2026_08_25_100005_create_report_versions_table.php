<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padanan `form_versions` untuk report.
 *
 * Asimetri ini sudah ditandai di docs/RANCANGAN.md §12: definisi form bisa
 * dikembalikan, definisi report tidak. Report justru lebih rawan — satu kolom
 * agregat yang salah diubah membuat seluruh angkanya keliru tanpa error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('snapshot');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['report_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
    }
};
