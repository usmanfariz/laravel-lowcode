<?php

namespace App\Services\Report;

use App\Models\Report;
use App\Models\ReportColumn;
use App\Models\ReportFilter;
use App\Models\User;
use App\Services\DataSourceResolver;
use App\Services\SqlExpressionGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun query report dari metadata.
 *
 * Semua identifier — tabel utama, tabel join, kolom, kolom filter, kolom
 * group by — dilewatkan DataSourceResolver. Yang tidak lolos melempar
 * exception, bukan diam-diam dilewati.
 */
class ReportQueryBuilder
{
    private const AGGREGATES = [
        'sum' => 'SUM', 'avg' => 'AVG', 'count' => 'COUNT',
        'count_distinct' => 'COUNT', 'min' => 'MIN', 'max' => 'MAX',
    ];

    public function __construct(
        private readonly DataSourceResolver $sources,
        private readonly SqlExpressionGuard $guard,
    ) {}

    /** Alias tabel yang sah dipakai di query ini: tabel utama + seluruh join. */
    public function aliases(Report $report): array
    {
        $aliases = [$report->alias() => $report->base_table];

        foreach ($report->joins as $join) {
            $aliases[$join->alias()] = $join->table_name;
        }

        return $aliases;
    }

    /**
     * Pecah "alias.kolom" menjadi identifier yang sudah diperiksa.
     *
     * Referensi tanpa alias dianggap milik tabel utama. Alias yang tidak
     * terdaftar ditolak — itu mencegah metadata menunjuk tabel yang tidak
     * ikut di-join.
     *
     * Publik karena builder memakainya untuk memvalidasi referensi kolom
     * sebelum disimpan, bukan hanya saat query dijalankan.
     */
    public function qualify(Report $report, string $reference): string
    {
        $aliases = $this->aliases($report);

        if (str_contains($reference, '.')) {
            [$alias, $column] = explode('.', $reference, 2);
        } else {
            $alias = $report->alias();
            $column = $reference;
        }

        if (! isset($aliases[$alias])) {
            throw new \RuntimeException("Alias '{$alias}' tidak dikenal pada report '{$report->code}'.");
        }

        $this->sources->assertColumn($aliases[$alias], $column);

        return $alias.'.'.$column;
    }

    public function base(Report $report, User $user): Builder
    {
        $this->sources->assertReadable($report->base_table);

        $query = DB::connection($report->connection ?: null)
            ->table($report->base_table.' as '.$report->alias());

        foreach ($report->joins as $join) {
            // Tabel join wajib punya izin baca sendiri.
            $this->sources->assertReadable($join->table_name);

            $method = match ($join->join_type) {
                'inner' => 'join',
                'right' => 'rightJoin',
                default => 'leftJoin',
            };

            $first = $this->qualify($report, $join->first_column);
            $operator = $this->operatorFor($join->operator);
            $second = $this->qualify($report, $join->second_column);
            $alias = $join->alias();

            // Baris terhapus pada tabel join disaring di klausa ON, bukan WHERE:
            // di WHERE, sebuah LEFT JOIN akan berubah sifat menjadi INNER dan
            // ikut membuang baris induk yang relasinya kosong.
            $hideDeleted = $report->use_soft_delete
                && in_array('deleted_at', $this->sources->allowedColumns($join->table_name), true);

            $query->{$method}(
                $join->table_name.' as '.$alias,
                function ($clause) use ($first, $operator, $second, $alias, $hideDeleted) {
                    $clause->on($first, $operator, $second);

                    if ($hideDeleted) {
                        $clause->whereNull($alias.'.deleted_at');
                    }
                }
            );
        }

        $this->applySoftDelete($query, $report);
        $this->applyScope($query, $report, $user);

        return $query;
    }

    /** Operator join dibatasi ke daftar tetap, tidak diambil mentah dari metadata. */
    private function operatorFor(string $operator): string
    {
        return match ($operator) {
            '!=' => '!=', '>' => '>', '>=' => '>=', '<' => '<', '<=' => '<=',
            default => '=',
        };
    }

    /**
     * Sembunyikan baris terhapus. Hanya berlaku bila tabel utama memang punya
     * kolom deleted_at, jadi flag ini aman dinyalakan untuk semua report.
     */
    private function applySoftDelete(Builder $query, Report $report): void
    {
        if (! $report->use_soft_delete) {
            return;
        }

        if (! in_array('deleted_at', $this->sources->allowedColumns($report->base_table), true)) {
            return;
        }

        $query->whereNull($report->alias().'.deleted_at');
    }

    private function applyScope(Builder $query, Report $report, User $user): void
    {
        if ($user->dataScope() === 'all' || ! $report->scope_column) {
            return;
        }

        $query->where(
            $this->qualify($report, $report->scope_column),
            $user->scope_value ?? '__tanpa_scope__'
        );
    }

    /** Ekspresi SELECT satu kolom report, sudah beralias aman. */
    public function selectFor(Report $report, ReportColumn $column, int $index): Expression
    {
        $alias = 'c'.$index;
        $sql = $this->rawFor($report, $column);

        return DB::raw($sql.' as '.$alias);
    }

    /** Bagian SQL kolom tanpa alias — dipakai select maupun order by. */
    private function rawFor(Report $report, ReportColumn $column): string
    {
        if ($column->source_type === 'expression') {
            return $this->expression($column);
        }

        $qualified = $this->qualify($report, $column->column_name);

        if (! $column->hasAggregate()) {
            return $qualified;
        }

        $function = self::AGGREGATES[$column->aggregate];

        return $column->aggregate === 'count_distinct'
            ? "COUNT(DISTINCT {$qualified})"
            : "{$function}({$qualified})";
    }

    /**
     * Ekspresi bebas masuk langsung ke SELECT, jadi dijaga dua lapis: izin
     * system.raw_query dan penyaringan pola.
     */
    private function expression(ReportColumn $column): string
    {
        if (! auth()->user()?->hasPermission('system.raw_query')) {
            throw new \RuntimeException("Kolom ekspresi butuh izin 'system.raw_query'.");
        }

        return $this->guard->assertSafe((string) $column->expression, "kolom '{$column->label}'");
    }

    /**
     * Terapkan filter dari request.
     *
     * @param  array<int, array<int, mixed>>  $input  dipetakan id filter → larik nilai
     */
    public function applyFilters(Builder $query, Report $report, array $input): void
    {
        foreach ($report->filters as $filter) {
            $values = $this->normalize($input[$filter->id] ?? $filter->default_values ?? []);

            $needed = $filter->valueCount();

            if ($needed === 0) {
                $this->applyNullFilter($query, $report, $filter);

                continue;
            }

            // Filter tanpa nilai dilewati, kecuali wajib.
            if ($values === []) {
                if ($filter->is_required) {
                    throw new \RuntimeException("Filter '{$filter->label}' wajib diisi.");
                }

                continue;
            }

            $this->applyValueFilter($query, $report, $filter, $values);
        }
    }

    private function applyNullFilter(Builder $query, Report $report, ReportFilter $filter): void
    {
        $column = $this->qualify($report, $filter->column_name);

        $filter->operator === 'is_null'
            ? $query->whereNull($column)
            : $query->whereNotNull($column);
    }

    private function applyValueFilter(Builder $query, Report $report, ReportFilter $filter, array $values): void
    {
        $column = $this->qualify($report, $filter->column_name);

        match ($filter->operator) {
            'between' => count($values) >= 2
                ? $query->whereBetween($column, [$values[0], $values[1]])
                // Satu ujung saja tetap berguna: jadikan >= atau <=.
                : $query->where($column, '>=', $values[0]),

            'in' => $query->whereIn($column, $values),
            'not_in' => $query->whereNotIn($column, $values),

            'like' => $query->where($column, 'like', '%'.$values[0].'%'),
            'not_like' => $query->where($column, 'not like', '%'.$values[0].'%'),

            default => $query->where($column, $this->comparisonFor($filter->operator), $values[0]),
        };
    }

    private function comparisonFor(string $operator): string
    {
        return match ($operator) {
            '!=' => '!=', '>' => '>', '>=' => '>=', '<' => '<', '<=' => '<=',
            default => '=',
        };
    }

    /** Nilai filter selalu dijadikan larik datar tanpa elemen kosong. */
    private function normalize(mixed $value): array
    {
        $values = array_values(array_filter(
            is_array($value) ? $value : [$value],
            fn ($v) => $v !== null && $v !== ''
        ));

        return $values;
    }

    public function applyGrouping(Builder $query, Report $report): void
    {
        $groupColumns = $report->columns->where('is_group_column', true);

        // Agregat tanpa group by menghasilkan satu baris ringkasan — itu sah
        // dan memang perilaku yang diharapkan report bertipe summary.
        if ($groupColumns->isEmpty()) {
            return;
        }

        foreach ($groupColumns as $column) {
            $query->groupBy(DB::raw($this->rawFor($report, $column)));
        }
    }

    public function applyOrder(Builder $query, Report $report, ?int $columnIndex, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $columns = $report->columns->where('is_visible', true)->values();

        $column = $columnIndex !== null ? $columns->get($columnIndex) : null;

        if ($column && $column->is_sortable) {
            $query->orderBy(DB::raw($this->rawFor($report, $column)), $direction);

            return;
        }

        if ($report->default_order_column) {
            $query->orderBy(
                DB::raw($this->qualify($report, $report->default_order_column)),
                $report->default_order_direction ?: 'asc'
            );
        }
    }

    public function applySearch(Builder $query, Report $report, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $searchable = $report->columns
            ->where('is_searchable', true)
            ->where('is_visible', true)
            ->reject(fn (ReportColumn $c) => $c->hasAggregate());

        if ($searchable->isEmpty()) {
            return;
        }

        $query->where(function (Builder $q) use ($report, $searchable, $search) {
            foreach ($searchable as $column) {
                $q->orWhere(DB::raw($this->rawFor($report, $column)), 'like', '%'.$search.'%');
            }
        });
    }
}
