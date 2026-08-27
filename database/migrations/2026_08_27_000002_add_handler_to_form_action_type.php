<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis aksi baru: `handler` — tombol yang menjalankan kode terdaftar.
 *
 * MySQL menyimpan pilihan ini sebagai ENUM sungguhan, jadi daftarnya harus
 * ditulis ulang. SQLite (dipakai test) meniru ENUM dengan CHECK constraint;
 * mengubahnya jadi kolom teks biasa lebih aman daripada menyusun ulang
 * constraint-nya, karena nilainya sudah dijaga validasi di sisi aplikasi.
 */
return new class extends Migration
{
    private const JENIS = ['route', 'url', 'ajax', 'modal', 'handler'];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement($this->mysqlEnum(self::JENIS));

            return;
        }

        Schema::table('form_actions', function (Blueprint $table) {
            $table->string('action_type', 20)->default('route')->change();
        });
    }

    public function down(): void
    {
        // Baris yang memakai jenis baru tidak punya padanan di daftar lama;
        // dikembalikan ke 'ajax' agar tombolnya tetap ada dan tidak menabrak
        // constraint saat daftar enum dipersempit.
        DB::table('form_actions')->where('action_type', 'handler')->update(['action_type' => 'ajax']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement($this->mysqlEnum(['route', 'url', 'ajax', 'modal']));

            return;
        }

        Schema::table('form_actions', function (Blueprint $table) {
            $table->string('action_type', 20)->default('route')->change();
        });
    }

    /** @param  array<int, string>  $jenis */
    private function mysqlEnum(array $jenis): string
    {
        $daftar = implode(',', array_map(fn ($j) => "'".$j."'", $jenis));

        return "ALTER TABLE form_actions MODIFY action_type ENUM({$daftar}) NOT NULL DEFAULT 'route'";
    }
};
