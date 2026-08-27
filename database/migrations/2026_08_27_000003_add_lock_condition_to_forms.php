<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penguncian baris berdasarkan isinya.
 *
 * Tanpa ini tidak ada yang mencegah nota yang sudah diposting disunting ulang:
 * tombol "Posting" bisa disembunyikan lewat show_condition, tapi form edit-nya
 * tetap terbuka lewat URL langsung.
 *
 * Bentuk `lock_condition` sengaja sama dengan `form_actions.show_condition`
 * — {"kolom": "nilai"} atau {"kolom": ["a","b"]} — supaya admin hanya perlu
 * memahami satu sintaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->json('lock_condition')->nullable()->after('scope_column');
            $table->string('lock_message', 255)->nullable()->after('lock_condition');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['lock_condition', 'lock_message']);
        });
    }
};
