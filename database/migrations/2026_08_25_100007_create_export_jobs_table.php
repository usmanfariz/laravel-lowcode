<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelacak berkas hasil ekspor terantre.
 *
 * Ekspor besar tidak bisa dikerjakan sinkron — request akan mati kehabisan
 * waktu. Barisnya menyimpan status dan lokasi berkas supaya pengguna bisa
 * mengunduhnya setelah selesai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('source_type', ['form', 'report']);
            $table->string('source_code', 100);
            $table->string('title', 150);
            $table->enum('format', ['xlsx', 'csv', 'pdf']);

            // Filter dan pencarian yang berlaku saat ekspor diminta.
            $table->json('params')->nullable();

            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->unsignedInteger('row_count')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
