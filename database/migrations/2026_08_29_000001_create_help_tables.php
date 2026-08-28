<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Basis pengetahuan chatbot bantuan.
 *
 * Jawabannya disimpan sebagai baris, bukan ditanam di Blade — sama seperti
 * form, report, dan dashboard. Alasannya sama pula: petunjuk pemakaian ikut
 * berubah setiap kali aplikasi berubah, dan yang paling tahu perubahannya
 * adalah admin yang mengaturnya, bukan yang menulis kodenya.
 *
 * Tidak ada layanan luar yang dipanggil. Pertanyaan pengguna tidak pernah
 * meninggalkan server ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();      // dipakai seeder agar idempoten
            $table->string('category', 100)->default('Umum');
            $table->string('question', 255);
            $table->text('answer');

            // Kata kunci tambahan, dipisah koma. Gunanya menangkap istilah yang
            // tidak muncul di pertanyaan: sinonim, singkatan, dan bunyi pesan
            // galat yang biasanya disalin-tempel pengguna.
            $table->string('keywords', 500)->nullable();

            // Tombol "buka halaman" pada balon jawaban.
            $table->string('link_route', 150)->nullable();
            $table->string('link_label', 100)->nullable();

            // Bila diisi, tombol di atas hanya tampil bagi yang punya izin ini.
            // Artikelnya sendiri tetap terbaca — menyembunyikan penjelasan cara
            // kerja aplikasi tidak melindungi apa pun, hanya membingungkan.
            $table->string('permission_code', 150)->nullable();

            $table->boolean('is_featured')->default(false); // muncul sebagai saran awal
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category', 'order_no']);
        });

        // Riwayat pertanyaan — dasar untuk melengkapi basis pengetahuan.
        // Tanpa ini, satu-satunya cara tahu jawaban apa yang kurang adalah
        // menunggu ada yang mengeluh.
        Schema::create('help_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 255);
            $table->foreignId('help_article_id')->nullable()->constrained('help_articles')->nullOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->boolean('is_answered')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['is_answered', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_queries');
        Schema::dropIfExists('help_articles');
    }
};
