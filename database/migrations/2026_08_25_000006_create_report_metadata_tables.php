<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // reports
        // ---------------------------------------------------------------
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();   // dipakai di URL: /reports/{code}
            $table->string('name', 150);
            $table->string('title', 150)->nullable();
            $table->string('description', 255)->nullable();

            $table->enum('type', ['table', 'summary', 'crosstab', 'chart'])->default('table');

            // builder = dirakit dari base_table + report_joins (aman, default)
            // raw     = SQL mentah, hanya boleh disunting superadmin dan
            //           wajib lolos validasi SELECT-only sebelum disimpan
            $table->enum('source_type', ['builder', 'raw'])->default('builder');

            $table->string('connection', 100)->default('mysql');
            $table->string('base_table', 150)->nullable();  // wajib terdaftar di data_sources
            $table->string('base_alias', 100)->nullable();
            $table->longText('raw_query')->nullable();

            $table->string('group_by', 255)->nullable();
            $table->string('having', 255)->nullable();
            $table->string('default_order_column', 100)->nullable();
            $table->enum('default_order_direction', ['asc', 'desc'])->default('asc');
            $table->unsignedSmallInteger('per_page')->default(25);

            // Row-level access, sama mekanismenya dengan forms.scope_column
            $table->string('scope_column', 100)->nullable();

            $table->string('permission_code', 150)->nullable();
            $table->boolean('allow_export_excel')->default(true);
            $table->boolean('allow_export_pdf')->default(true);
            $table->boolean('allow_export_csv')->default(true);
            $table->boolean('allow_print')->default(true);

            // Di atas ambang ini, export dialihkan ke queue lalu user diberi
            // tautan unduh. Mencegah timeout dan kehabisan memori.
            $table->unsignedInteger('export_queue_threshold')->default(5000);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // report_joins
        // ---------------------------------------------------------------
        Schema::create('report_joins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->enum('join_type', ['inner', 'left', 'right'])->default('left');
            $table->string('table_name', 150);       // wajib terdaftar di data_sources
            $table->string('table_alias', 100)->nullable();

            $table->string('first_column', 150);     // contoh: sales.customer_id
            $table->enum('operator', ['=', '!=', '>', '>=', '<', '<='])->default('=');
            $table->string('second_column', 150);    // contoh: customers.id

            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['report_id', 'order_no']);
        });

        // ---------------------------------------------------------------
        // report_columns
        // ---------------------------------------------------------------
        Schema::create('report_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('label', 150);

            $table->enum('source_type', ['column', 'expression'])->default('column');
            $table->string('column_name', 150)->nullable();   // contoh: customers.name
            $table->string('expression', 255)->nullable();     // hanya untuk superadmin
            $table->string('column_alias', 100)->nullable();

            $table->enum('aggregate', ['none', 'sum', 'avg', 'count', 'count_distinct', 'min', 'max'])
                  ->default('none');

            $table->enum('format', [
                'text', 'number', 'decimal', 'currency', 'percentage',
                'date', 'datetime', 'boolean', 'badge',
            ])->default('text');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->enum('align', ['left', 'center', 'right'])->default('left');
            $table->string('width', 20)->nullable();

            $table->boolean('is_visible')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_group_column')->default(false); // pemecah grup pada report summary
            $table->boolean('show_total')->default(false);      // tampilkan subtotal / grand total

            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['report_id', 'order_no']);
        });

        // ---------------------------------------------------------------
        // report_filters
        // ---------------------------------------------------------------
        Schema::create('report_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('label', 150);
            $table->string('column_name', 150);   // divalidasi terhadap kolom yang tersedia

            $table->enum('operator', [
                '=', '!=', '>', '>=', '<', '<=',
                'like', 'not_like', 'between', 'in', 'not_in',
                'is_null', 'is_not_null',
            ])->default('=');

            $table->enum('input_type', [
                'text', 'number', 'date', 'date_range', 'datetime',
                'select', 'select2', 'multi_select', 'checkbox', 'radio',
            ])->default('text');

            $table->enum('data_source_type', ['none', 'static', 'table'])->default('none');
            $table->string('data_source', 150)->nullable();
            $table->string('value_field', 100)->nullable();
            $table->string('label_field', 100)->nullable();
            $table->json('data_filter')->nullable();
            $table->json('static_options')->nullable();

            $table->string('default_value', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('width')->default(3); // grid bootstrap
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['report_id', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_columns');
        Schema::dropIfExists('report_joins');
        Schema::dropIfExists('reports');
    }
};
