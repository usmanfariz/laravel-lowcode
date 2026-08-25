<?php

namespace App\Services\Generator;

use App\Models\Form;
use App\Models\User;
use App\Services\DataSourceResolver;
use App\Services\Form\FormService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat definisi form lengkap dari struktur satu tabel.
 *
 * Hasilnya hanya titik awal: pengguna tetap menyunting field lewat builder.
 * Karena itu generator memilih tebakan yang aman, bukan yang paling pintar.
 */
class FormGenerator
{
    /** Kandidat kolom label untuk relasi, diperiksa berurutan. */
    private const LABEL_CANDIDATES = ['name', 'nama', 'title', 'judul', 'label', 'code', 'kode'];

    public function __construct(
        private readonly TableInspector $inspector,
        private readonly ColumnMapper $mapper,
        private readonly DataSourceResolver $sources,
        private readonly FormService $forms,
    ) {}

    /**
     * Pratinjau field yang akan dibuat, tanpa menyentuh database.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preview(string $table): Collection
    {
        $order = 0;

        return $this->inspector->columns($table)
            ->map(fn (array $column) => $this->mapColumn($table, $column, ++$order))
            ->filter()
            ->values();
    }

    /** @return array<string, mixed>|null */
    private function mapColumn(string $table, array $column, int $order): ?array
    {
        $field = $this->mapper->toField($column, $order);

        if ($field === null) {
            return null;
        }

        // Kolom enum membaca nilainya dari tabel asalnya sendiri.
        if ($field['data_source_type'] === 'enum') {
            $field['data_source'] = $table;
        }

        // Relasi butuh kolom label; tebak dari struktur tabel tujuan.
        if ($field['data_source_type'] === 'table') {
            $field['label_field'] = $this->guessLabelColumn($field['data_source'], $field['value_field']);
            $field['data_order_by'] = $field['label_field'];
        }

        $field['_column'] = $column;

        return $field;
    }

    /**
     * Kolom yang paling masuk akal jadi label relasi. Bila tabel tujuan tidak
     * ada di whitelist, relasinya diturunkan jadi input angka biasa — lebih
     * baik daripada membuat select yang pasti gagal saat dibuka.
     */
    private function guessLabelColumn(string $table, string $valueField): ?string
    {
        try {
            $columns = $this->sources->allowedColumns($table);
        } catch (\Throwable) {
            return null;
        }

        foreach (self::LABEL_CANDIDATES as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        // Tidak ada kandidat: pakai kolom teks pertama selain primary key.
        foreach ($columns as $column) {
            if ($column !== $valueField && ! str_ends_with($column, '_id')) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Buat form beserta field, kolom list, permission, dan menu.
     *
     * @param  array<int, string>  $only  nama kolom yang dipilih; kosong = semua
     */
    public function generate(string $table, array $options, User $user, array $only = []): Form
    {
        $this->sources->assertReadable($table);

        $code = $options['code'];

        if (Form::where('code', $code)->exists()) {
            throw new \RuntimeException("Form dengan kode '{$code}' sudah ada.");
        }

        $fields = $this->preview($table);

        if ($only !== []) {
            $fields = $fields->filter(fn ($f) => in_array($f['field_name'], $only, true))->values();
        }

        if ($fields->isEmpty()) {
            throw new \RuntimeException('Tidak ada kolom yang dapat dijadikan field.');
        }

        return DB::transaction(function () use ($table, $options, $user, $fields, $code) {
            $form = $this->createForm($table, $options, $user, $code);

            $order = 0;
            foreach ($fields as $field) {
                $this->createField($form, $field, ++$order);
            }

            $this->createListColumns($form, $fields);
            $this->createPermissions($form, $options['permission_prefix']);

            if (! empty($options['create_menu'])) {
                $this->createMenu($form);
            }

            $this->forms->flush($code);

            return $form;
        });
    }

    private function createForm(string $table, array $options, User $user, string $code): Form
    {
        $columns = $this->sources->allowedColumns($table);

        return Form::create([
            'code' => $code,
            'name' => $options['name'],
            'title' => $options['title'] ?: $options['name'],
            'description' => $options['description'] ?? null,
            'connection' => 'mysql',
            'table_name' => $table,
            'primary_key' => $this->inspector->primaryKey($table) ?: 'id',
            'key_type' => 'increment',
            'type' => 'single',
            'layout_columns' => 2,
            'scope_column' => $options['scope_column'] ?: null,
            // Flag disesuaikan struktur nyata, bukan disetel membabi buta.
            'use_soft_delete' => in_array('deleted_at', $columns, true),
            'use_audit_column' => in_array('created_by', $columns, true)
                || in_array('updated_by', $columns, true),
            'default_order_column' => $this->inspector->primaryKey($table) ?: 'id',
            'default_order_direction' => 'desc',
            'per_page' => 25,
            'permission_prefix' => $options['permission_prefix'],
            'allow_create' => true,
            'allow_edit' => true,
            'allow_delete' => true,
            'allow_export' => true,
            'allow_print' => true,
            'is_active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function createField(Form $form, array $field, int $order): void
    {
        $column = $field['_column'];
        unset($field['_column']);

        $id = DB::table('form_fields')->insertGetId([
            ...$field,
            'form_id' => $form->id,
            'form_detail_id' => null,
            'order_no' => $order,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enum tetap diberi opsi statis sebagai cadangan, supaya form tidak
        // kosong bila tabel sumbernya nanti dicabut dari whitelist.
        if ($field['data_source_type'] === 'enum' && $column['enum_values'] !== []) {
            $rows = [];
            $n = 0;
            foreach ($column['enum_values'] as $value) {
                $rows[] = [
                    'form_field_id' => $id,
                    'value' => $value,
                    'label' => ucwords(str_replace('_', ' ', $value)),
                    'order_no' => ++$n,
                    'is_default' => false,
                    'is_active' => true,
                ];
            }
            DB::table('form_field_options')->insert($rows);
        }
    }

    /** Kolom halaman list: 6 field pertama yang layak ditampilkan. */
    private function createListColumns(Form $form, Collection $fields): void
    {
        $order = 0;

        foreach ($fields as $field) {
            if ($order >= 6) {
                break;
            }

            if (in_array($field['input_type'], ['textarea', 'editor', 'password', 'file'], true)) {
                continue;
            }

            // Field relasi ditampilkan sebagai nama, bukan id-nya.
            $isRelation = $field['data_source_type'] === 'table' && $field['label_field'];

            DB::table('form_list_columns')->insert([
                'form_id' => $form->id,
                'label' => $field['label'],
                'source_type' => $isRelation ? 'relation' : 'column',
                'column_name' => $field['field_name'],
                'relation_table' => $isRelation ? $field['data_source'] : null,
                'relation_key' => $isRelation ? $field['value_field'] : null,
                'relation_label' => $isRelation ? $field['label_field'] : null,
                // Kolom relasi menampilkan nama, bukan status — badge tidak cocok.
                'format' => $isRelation ? 'text' : $this->listFormat($field['input_type']),
                'align' => in_array($field['input_type'], ['number', 'decimal', 'currency', 'percentage'], true)
                    ? 'right' : 'left',
                'is_visible' => true,
                'is_searchable' => ! $isRelation && in_array($field['input_type'], ['text', 'email', 'url'], true),
                'is_sortable' => ! $isRelation,
                'order_no' => ++$order,
            ]);
        }
    }

    private function listFormat(string $inputType): string
    {
        return match ($inputType) {
            'number' => 'number',
            'decimal' => 'decimal',
            'currency' => 'currency',
            'percentage' => 'percentage',
            'date' => 'date',
            'datetime' => 'datetime',
            'switch', 'checkbox' => 'boolean',
            'select', 'select2' => 'badge',
            'image' => 'image',
            default => 'text',
        };
    }

    /**
     * Permission mengikuti konvensi <prefix>.<action> dan langsung diberikan
     * ke superadmin — tanpa ini form baru selalu 403, bahkan bagi superadmin.
     */
    private function createPermissions(Form $form, string $prefix): void
    {
        $created = [];

        foreach (['view', 'create', 'edit', 'delete', 'export', 'print'] as $action) {
            $code = $prefix.'.'.$action;

            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $form->name.': '.$action,
                    'group_name' => $form->name,
                    'is_system' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $created[] = $code;
        }

        $superadminId = DB::table('roles')->where('code', 'superadmin')->value('id');

        if (! $superadminId) {
            return;
        }

        foreach (DB::table('permissions')->whereIn('code', $created)->pluck('id') as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superadminId, 'permission_id' => $permissionId], []
            );
        }
    }

    private function createMenu(Form $form): void
    {
        DB::table('menus')->updateOrInsert(
            ['code' => 'form.'.$form->code],
            [
                'parent_id' => null,
                'name' => $form->name,
                'icon' => 'fas fa-table',
                'link_type' => 'form',
                'target_value' => $form->code,
                'permission_code' => $form->permission('view'),
                'order_no' => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        app(\App\Services\MenuService::class)->flush();
    }

    /** Usulan kode form dan prefix permission dari nama tabel. */
    public function suggest(string $table): array
    {
        $base = Str::of($table)->replace('demo_', '')->singular()->snake()->toString();

        return [
            'code' => $base,
            'name' => Str::of($table)->replace('_', ' ')->title()->toString(),
            'permission_prefix' => $base,
        ];
    }
}
