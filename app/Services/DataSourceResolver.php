<?php

namespace App\Services;

use App\Exceptions\DataSourceException;
use App\Models\DataSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gerbang tunggal menuju tabel bisnis.
 *
 * Setiap nama tabel dan kolom yang berasal dari metadata WAJIB melewati kelas
 * ini sebelum masuk query. Metadata dapat disunting lewat UI builder, jadi
 * isinya diperlakukan sebagai masukan yang tidak dipercaya — sama seperti input
 * dari request.
 */
class DataSourceResolver
{
    /** Nama tabel/kolom yang sah di MySQL, sekaligus menolak backtick dan spasi. */
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** Whitelist yang sudah divalidasi, dipakai ulang dalam satu request. */
    private array $resolved = [];

    public function source(string $table): DataSource
    {
        if (! preg_match(self::IDENTIFIER, $table)) {
            throw DataSourceException::notWhitelisted($table);
        }

        return $this->resolved[$table] ??= DataSource::query()
            ->where('table_name', $table)
            ->where('is_active', true)
            ->first() ?? throw DataSourceException::notWhitelisted($table);
    }

    public function assertReadable(string $table): DataSource
    {
        $source = $this->source($table);

        if (! $source->is_readable) {
            throw DataSourceException::notReadable($table);
        }

        return $source;
    }

    public function assertWritable(string $table): DataSource
    {
        $source = $this->source($table);

        if (! $source->is_writable) {
            throw DataSourceException::notWritable($table);
        }

        return $source;
    }

    /** Kolom nyata tabel menurut skema, dikurangi blocked_columns. */
    public function allowedColumns(string $table): array
    {
        $source = $this->assertReadable($table);

        $columns = Cache::remember(
            "datasource.columns.{$table}",
            now()->addMinutes(30),
            fn () => Schema::connection($source->connection ?: null)->getColumnListing($table)
        );

        return array_values(array_diff($columns, $source->blocked_columns ?? []));
    }

    /**
     * Pastikan satu kolom boleh dipakai. Dipanggil sebelum nama kolom masuk
     * select, where, atau order by.
     */
    public function assertColumn(string $table, string $column): string
    {
        if (! preg_match(self::IDENTIFIER, $column)) {
            throw DataSourceException::unknownColumn($table, $column);
        }

        $source = $this->assertReadable($table);

        if (in_array($column, $source->blocked_columns ?? [], true)) {
            throw DataSourceException::blockedColumn($table, $column);
        }

        if (! in_array($column, $this->allowedColumns($table), true)) {
            throw DataSourceException::unknownColumn($table, $column);
        }

        return $column;
    }

    /** Query builder pada tabel yang sudah lolos whitelist. */
    public function query(string $table): Builder
    {
        $source = $this->assertReadable($table);

        return DB::connection($source->connection ?: null)->table($table);
    }

    /**
     * Daftar opsi untuk field select bertipe data_source_type = 'table'.
     *
     * @param  array<string, mixed>|null  $filter  kondisi where tambahan dari metadata
     * @return Collection<int, array{value: mixed, label: string}>
     */
    public function options(
        string $table,
        string $valueField,
        string $labelField,
        ?array $filter = null,
        ?string $orderBy = null,
        int $limit = 500,
    ): Collection {
        $this->assertColumn($table, $valueField);
        $this->assertColumn($table, $labelField);

        $query = $this->query($table)->select([$valueField, $labelField]);

        foreach ($filter ?? [] as $column => $value) {
            // Nama kolom filter berasal dari metadata, jadi diperiksa juga.
            $this->assertColumn($table, (string) $column);

            is_array($value)
                ? $query->whereIn($column, $value)
                : $query->where($column, $value);
        }

        if ($orderBy) {
            $query->orderBy($this->assertColumn($table, $orderBy));
        } else {
            $query->orderBy($labelField);
        }

        return $query->limit($limit)->get()->map(fn ($row) => [
            'value' => $row->{$valueField},
            'label' => (string) $row->{$labelField},
        ]);
    }

    public function flushColumns(string $table): void
    {
        Cache::forget("datasource.columns.{$table}");
    }
}
