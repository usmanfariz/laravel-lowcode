<?php

namespace App\Services\Generator;

use App\Services\DataSourceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membaca struktur tabel dari information_schema.
 *
 * Hanya membaca — tidak pernah menjalankan DDL. Tabel yang boleh diperiksa
 * tetap dibatasi whitelist data_sources, sama seperti jalur lain.
 */
class TableInspector
{
    public function __construct(private readonly DataSourceResolver $sources) {}

    /**
     * @return Collection<int, array{
     *     name: string, type: string, data_type: string, length: int|null,
     *     precision: int|null, scale: int|null, nullable: bool, default: string|null,
     *     is_primary: bool, is_auto: bool, is_unique: bool, comment: string|null,
     *     enum_values: array<int, string>, references: array{table: string, column: string}|null
     * }>
     */
    public function columns(string $table): Collection
    {
        $source = $this->sources->assertReadable($table);
        $connection = DB::connection($source->connection ?: null);
        $blocked = $source->blocked_columns ?? [];

        $rows = $connection->select(
            // Alias huruf kecil ditulis eksplisit: MySQL 8 mengembalikan nama
            // kolom information_schema dalam huruf besar.
            'SELECT column_name AS `column_name`, column_type AS `column_type`,
                    data_type AS `data_type`, is_nullable AS `is_nullable`,
                    column_default AS `column_default`, column_key AS `column_key`,
                    extra AS `extra`, column_comment AS `column_comment`,
                    character_maximum_length AS `character_maximum_length`,
                    numeric_precision AS `numeric_precision`,
                    numeric_scale AS `numeric_scale`
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ordinal_position',
            [$table]
        );

        $foreignKeys = $this->foreignKeys($connection, $table);
        $uniques = $this->uniqueColumns($connection, $table);

        return collect($rows)
            // Kolom terblokir tidak boleh muncul sebagai field, sekalipun ada
            // di tabel — inilah gunanya blocked_columns.
            ->reject(fn ($r) => in_array($r->column_name, $blocked, true))
            ->map(fn ($r) => [
                'name' => $r->column_name,
                'type' => $r->column_type,
                'data_type' => strtolower($r->data_type),
                'length' => $r->character_maximum_length ? (int) $r->character_maximum_length : null,
                'precision' => $r->numeric_precision ? (int) $r->numeric_precision : null,
                'scale' => $r->numeric_scale !== null ? (int) $r->numeric_scale : null,
                'nullable' => $r->is_nullable === 'YES',
                'default' => $r->column_default,
                'is_primary' => $r->column_key === 'PRI',
                'is_auto' => str_contains(strtolower($r->extra ?? ''), 'auto_increment'),
                'is_unique' => in_array($r->column_name, $uniques, true),
                'comment' => $r->column_comment ?: null,
                'enum_values' => $this->enumValues($r->column_type),
                'references' => $foreignKeys[$r->column_name] ?? null,
            ])
            ->values();
    }

    public function primaryKey(string $table): ?string
    {
        return $this->columns($table)->firstWhere('is_primary', true)['name'] ?? null;
    }

    /** @return array<string, array{table: string, column: string}> */
    private function foreignKeys($connection, string $table): array
    {
        $rows = $connection->select(
            'SELECT column_name AS `column_name`,
                    referenced_table_name AS `referenced_table_name`,
                    referenced_column_name AS `referenced_column_name`
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE() AND table_name = ?
               AND referenced_table_name IS NOT NULL',
            [$table]
        );

        $keys = [];
        foreach ($rows as $row) {
            $keys[$row->column_name] = [
                'table' => $row->referenced_table_name,
                'column' => $row->referenced_column_name,
            ];
        }

        return $keys;
    }

    /** Kolom yang punya indeks unik satu-kolom. Indeks gabungan diabaikan. */
    private function uniqueColumns($connection, string $table): array
    {
        $rows = $connection->select(
            'SELECT index_name AS `index_name`, column_name AS `column_name`,
                    COUNT(*) OVER (PARTITION BY index_name) AS `cols`
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0',
            [$table]
        );

        return collect($rows)
            ->filter(fn ($r) => (int) $r->cols === 1 && $r->index_name !== 'PRIMARY')
            ->pluck('column_name')
            ->unique()
            ->all();
    }

    /** @return array<int, string> */
    private function enumValues(string $columnType): array
    {
        if (! str_starts_with(strtolower($columnType), 'enum(')) {
            return [];
        }

        preg_match_all("/'((?:[^']|'')*)'/", $columnType, $matches);

        return array_map(fn ($v) => str_replace("''", "'", $v), $matches[1]);
    }

    /**
     * Seluruh tabel fisik di database, terlepas dari whitelist.
     *
     * MELEWATI data_sources dengan sengaja: halaman pengelola sumber data
     * justru butuh melihat tabel yang BELUM terdaftar. Karena itu pemanggilnya
     * wajib dijaga permission system.data_source.
     *
     * @return Collection<int, array{name: string, rows: int, comment: string|null}>
     */
    public function physicalTables(): Collection
    {
        $rows = DB::select(
            'SELECT table_name AS `name`, table_rows AS `rows`, table_comment AS `comment`
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\'
             ORDER BY table_name'
        );

        return collect($rows)->map(fn ($r) => [
            'name' => $r->name,
            // table_rows pada InnoDB hanya perkiraan; cukup untuk memberi
            // gambaran besar-kecilnya tabel, bukan angka yang dipakai hitung.
            'rows' => (int) ($r->rows ?? 0),
            'comment' => $r->comment ?: null,
        ]);
    }

    /**
     * Nama kolom satu tabel tanpa melalui whitelist.
     *
     * Dipakai halaman sumber data untuk memilih blocked_columns pada tabel
     * yang baru akan didaftarkan — saat itu tabelnya memang belum ada di
     * whitelist, sehingga allowedColumns() belum bisa dipakai.
     *
     * @return array<int, string>
     */
    public function rawColumns(string $table): array
    {
        // Nama tabel tetap diperiksa polanya walau tidak lewat whitelist.
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            return [];
        }

        return collect(DB::select(
            'SELECT column_name AS `name` FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ordinal_position',
            [$table]
        ))->pluck('name')->all();
    }

    public function tableExists(string $table): bool
    {
        return $this->rawColumns($table) !== [];
    }

    /** Tabel yang boleh dipakai generator: seluruh isi whitelist yang aktif. */
    public function availableTables(): Collection
    {
        return \App\Models\DataSource::query()
            ->where('is_active', true)
            ->where('is_readable', true)
            ->orderBy('table_name')
            ->get();
    }
}
