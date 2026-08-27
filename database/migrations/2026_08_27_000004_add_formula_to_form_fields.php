<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field terhitung: nilainya diturunkan dari field lain, bukan diketik.
 *
 * Rumusnya diuraikan parser sendiri (App\Support\FormulaEvaluator), tidak
 * pernah diserahkan ke eval() maupun ke SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('formula', 255)->nullable()->after('show_total');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
