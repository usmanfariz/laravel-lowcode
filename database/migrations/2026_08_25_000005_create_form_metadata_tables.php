<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // forms — definisi satu halaman CRUD
        // ---------------------------------------------------------------
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();   // dipakai di URL: /forms/{code}
            $table->string('name', 150);
            $table->string('title', 150)->nullable();
            $table->string('description', 255)->nullable();

            $table->string('connection', 100)->default('mysql');
            $table->string('table_name', 150);        // wajib terdaftar di data_sources
            $table->string('primary_key', 100)->default('id');
            $table->enum('key_type', ['increment', 'uuid', 'manual'])->default('increment');

            $table->enum('type', ['single', 'master_detail', 'wizard'])->default('single');
            $table->unsignedTinyInteger('layout_columns')->default(2); // 1, 2, atau 3 kolom

            // Row-level access. Bila role user ber-data_scope = branch,
            // engine menambahkan where scope_column = users.scope_value.
            $table->string('scope_column', 100)->nullable();

            $table->boolean('use_soft_delete')->default(false);
            $table->boolean('use_audit_column')->default(true); // created_by / updated_by

            $table->string('default_order_column', 100)->nullable();
            $table->enum('default_order_direction', ['asc', 'desc'])->default('desc');
            $table->unsignedSmallInteger('per_page')->default(25);

            $table->string('permission_prefix', 100)->nullable(); // contoh: customer -> customer.create
            $table->boolean('allow_create')->default(true);
            $table->boolean('allow_edit')->default(true);
            $table->boolean('allow_delete')->default(true);
            $table->boolean('allow_export')->default(true);
            $table->boolean('allow_print')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('table_name');
        });

        // ---------------------------------------------------------------
        // form_details — tabel anak untuk form master-detail
        // ---------------------------------------------------------------
        Schema::create('form_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('title', 150);

            $table->string('table_name', 150);          // wajib terdaftar di data_sources
            $table->string('primary_key', 100)->default('id');
            $table->string('foreign_key', 100);         // kolom di tabel anak yang menunjuk ke induk

            $table->unsignedSmallInteger('min_rows')->default(0);
            $table->unsignedSmallInteger('max_rows')->nullable();
            $table->boolean('allow_add')->default(true);
            $table->boolean('allow_delete')->default(true);
            $table->boolean('show_total_row')->default(false);

            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'code']);
        });

        // ---------------------------------------------------------------
        // form_fields — definisi input. Dipakai form induk maupun detail.
        // ---------------------------------------------------------------
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();

            // Null = field milik form induk. Terisi = field milik baris detail.
            $table->foreignId('form_detail_id')->nullable()->constrained('form_details')->cascadeOnDelete();

            $table->string('field_name', 100);   // nama kolom asli di tabel
            $table->string('label', 150);

            $table->enum('input_type', [
                'text', 'textarea', 'number', 'decimal', 'currency', 'percentage',
                'email', 'password', 'url', 'date', 'datetime', 'time',
                'select', 'select2', 'multi_select', 'ajax_select', 'autocomplete',
                'radio', 'checkbox', 'switch', 'file', 'image', 'hidden', 'editor',
            ])->default('text');

            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->string('default_value', 255)->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->string('help_text', 255)->nullable();

            $table->unsignedTinyInteger('width')->default(12); // grid bootstrap 1-12
            $table->unsignedInteger('order_no')->default(0);
            $table->string('validation', 255)->nullable();     // contoh: max:100|min:3

            // Sumber data untuk select / radio / checkbox
            $table->enum('data_source_type', ['none', 'static', 'table', 'enum'])->default('none');
            $table->string('data_source', 150)->nullable();     // nama tabel, wajib ada di data_sources
            $table->string('value_field', 100)->nullable();
            $table->string('label_field', 100)->nullable();
            $table->json('data_filter')->nullable();            // kondisi where tambahan
            $table->string('data_order_by', 100)->nullable();

            // Dropdown bertingkat: kabupaten mengikuti provinsi
            $table->string('depends_on', 100)->nullable();      // field_name induk
            $table->string('depends_column', 100)->nullable();  // kolom filter di tabel sumber

            // Tampil/sembunyi bersyarat, contoh:
            // {"field":"jenis","operator":"=","value":"badan"}
            $table->json('show_condition')->nullable();

            // Khusus file / image
            $table->string('upload_path', 255)->nullable();
            $table->string('allowed_extensions', 255)->nullable(); // jpg,png,pdf
            $table->unsignedInteger('max_file_size')->nullable();  // dalam KB

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'form_detail_id', 'field_name'], 'form_fields_unique_field');
            $table->index(['form_id', 'order_no']);
        });

        // ---------------------------------------------------------------
        // form_field_options — opsi statis (data_source_type = static)
        // ---------------------------------------------------------------
        Schema::create('form_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->string('value', 150);
            $table->string('label', 150);
            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unique(['form_field_id', 'value'], 'form_field_options_unique_value');
        });

        // ---------------------------------------------------------------
        // form_list_columns — kolom pada halaman index (DataTables)
        // Sengaja dipisah dari form_fields karena kolom list sering berasal
        // dari join atau ekspresi, bukan kolom mentah tabel.
        // ---------------------------------------------------------------
        Schema::create('form_list_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label', 150);

            $table->enum('source_type', ['column', 'relation', 'expression'])->default('column');
            $table->string('column_name', 150)->nullable();  // untuk source_type = column

            // untuk source_type = relation
            $table->string('relation_table', 150)->nullable();
            $table->string('relation_key', 100)->nullable();
            $table->string('relation_label', 100)->nullable();

            // untuk source_type = expression (hanya boleh diisi superadmin, divalidasi ketat)
            $table->string('expression', 255)->nullable();

            $table->enum('format', [
                'text', 'number', 'decimal', 'currency', 'percentage',
                'date', 'datetime', 'boolean', 'badge', 'image', 'link',
            ])->default('text');
            $table->json('format_options')->nullable(); // mis. peta warna badge
            $table->enum('align', ['left', 'center', 'right'])->default('left');
            $table->string('width', 20)->nullable();

            $table->boolean('is_visible')->default(true);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->unsignedInteger('order_no')->default(0);

            $table->index(['form_id', 'order_no']);
        });

        // ---------------------------------------------------------------
        // form_actions — tombol tambahan di luar CRUD standar
        // ---------------------------------------------------------------
        Schema::create('form_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('label', 150);
            $table->string('icon', 100)->nullable();

            $table->enum('position', ['toolbar', 'row', 'bulk'])->default('row');
            $table->enum('action_type', ['route', 'url', 'ajax', 'modal'])->default('route');
            $table->string('target_value', 255)->nullable();
            $table->enum('http_method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])->default('GET');

            $table->string('permission_code', 150)->nullable();
            $table->string('confirm_message', 255)->nullable();
            $table->json('show_condition')->nullable();   // tombol muncul bergantung isi baris
            $table->string('css_class', 100)->default('btn btn-sm btn-primary');

            $table->unsignedInteger('order_no')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unique(['form_id', 'code']);
        });

        // ---------------------------------------------------------------
        // form_versions — snapshot metadata sebelum diubah, untuk rollback
        // ---------------------------------------------------------------
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('snapshot');            // JSON gabungan form + seluruh anaknya
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('form_actions');
        Schema::dropIfExists('form_list_columns');
        Schema::dropIfExists('form_field_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_details');
        Schema::dropIfExists('forms');
    }
};
