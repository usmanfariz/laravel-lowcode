<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widget dashboard.
 *
 * Dashboard sebelumnya Blade statis dengan angka yang di-hardcode. Tabel ini
 * membuatnya metadata-driven seperti form dan report.
 *
 * Widget bertipe `chart` dan `table` sengaja menumpang report yang sudah ada,
 * bukan punya mesin query sendiri: seluruh whitelist, scope per baris, dan
 * permission-nya otomatis ikut berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('title', 150);
            $table->enum('type', ['stat', 'chart', 'table', 'text'])->default('stat');

            // Tampilan
            $table->string('icon', 100)->nullable();
            $table->string('color', 30)->default('info');
            $table->unsignedTinyInteger('width')->default(3);
            $table->string('link_url', 255)->nullable();

            // type = stat
            $table->string('source_table', 150)->nullable();
            $table->string('source_column', 100)->nullable();
            $table->enum('aggregate', ['count', 'sum', 'avg', 'min', 'max'])->default('count');
            $table->json('filter')->nullable();
            $table->enum('format', ['number', 'decimal', 'currency', 'percentage'])->default('number');

            // type = chart | table — menunjuk reports.code
            $table->string('report_code', 100)->nullable();
            $table->unsignedSmallInteger('row_limit')->default(5);

            // type = text
            $table->text('content')->nullable();

            $table->string('permission_code', 150)->nullable();
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
