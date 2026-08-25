<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormListColumn;
use App\Models\User;
use App\Services\DataSourceResolver;
use App\Services\SqlExpressionGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun query halaman list dari form_list_columns.
 *
 * Setiap nama kolom dan tabel yang berasal dari metadata dilewatkan
 * DataSourceResolver lebih dulu; tidak ada satu pun identifier yang masuk
 * query tanpa diperiksa.
 */
class FormQueryBuilder
{
    public function __construct(
        private readonly DataSourceResolver $sources,
        private readonly SqlExpressionGuard $guard,
    ) {}

    /** @return Collection<int, FormListColumn> */
    public function columns(Form $form): Collection
    {
        $columns = $form->listColumns->where('is_visible', true)->values();

        // Form tanpa definisi kolom list memakai field form sebagai
        // gantinya, supaya halaman index tetap berguna sebelum dikonfigurasi.
        if ($columns->isEmpty()) {
            $columns = $form->fields
                ->where('input_type', '!=', 'hidden')
                ->take(6)
                ->map(fn ($f) => new FormListColumn([
                    'form_id' => $form->id,
                    'label' => $f->label,
                    'source_type' => 'column',
                    'column_name' => $f->field_name,
                    'format' => 'text',
                    'align' => 'left',
                    'is_visible' => true,
                    'is_searchable' => true,
                    'is_sortable' => true,
                ]))
                ->values();
        }

        return $columns;
    }

    public function base(Form $form, User $user): Builder
    {
        $table = $form->table_name;
        $query = $this->sources->query($table);

        if ($form->use_soft_delete) {
            $query->whereNull($table.'.deleted_at');
        }

        $this->applyScope($query, $form, $user);

        return $query;
    }

    /**
     * Batas data per baris. Role dengan data_scope selain 'all' hanya melihat
     * baris yang cocok dengan users.scope_value lewat forms.scope_column.
     */
    private function applyScope(Builder $query, Form $form, User $user): void
    {
        $scope = $user->dataScope();

        if ($scope === 'all' || ! $form->scope_column) {
            return;
        }

        $column = $this->sources->assertColumn($form->table_name, $form->scope_column);

        if ($scope === 'own') {
            // 'own' dibandingkan ke kolom audit bila ada, kalau tidak ke scope_column.
            $owner = in_array('created_by', $this->sources->allowedColumns($form->table_name), true)
                ? 'created_by'
                : $column;

            $query->where($form->table_name.'.'.$owner, $user->id);

            return;
        }

        // 'branch' dan 'custom' sama-sama membandingkan ke scope_value.
        // scope_value kosong berarti tidak ada baris yang cocok — sengaja
        // menutup, bukan membuka.
        $query->where($form->table_name.'.'.$column, $user->scope_value ?? '__tanpa_scope__');
    }

    /** Terapkan select, join relasi, pencarian, sorting, dan paging. */
    public function paginate(Form $form, User $user, array $params): array
    {
        $table = $form->table_name;
        $columns = $this->columns($form);

        $query = $this->base($form, $user);
        $total = (clone $query)->count();

        $selects = [$table.'.'.$this->sources->assertColumn($table, $form->primary_key).' as __id'];
        $joined = [];

        foreach ($columns as $i => $column) {
            $selects[] = $this->selectFor($form, $column, $i, $joined, $query);
        }

        $this->applySearch($query, $form, $columns, $params['search'] ?? null);
        $filtered = (clone $query)->count();

        $this->applyOrder($query, $form, $columns, $params);

        $rows = $query->select($selects)
            ->skip(max(0, (int) ($params['start'] ?? 0)))
            ->take(min(max(1, (int) ($params['length'] ?? $form->per_page ?: setting('per_page', 25))), 200))
            ->get();

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows, 'columns' => $columns];
    }

    /**
     * Ekspresi SELECT untuk satu kolom list.
     *
     * Kolom biasa dan relasi dikembalikan sebagai string agar Laravel yang
     * mengutip identifier-nya. Ekspresi harus dibungkus DB::raw: string biasa
     * akan dikutip sebagai satu nama kolom, sehingga "price * stock" menjadi
     * `price * stock` dan query gagal.
     */
    private function selectFor(Form $form, FormListColumn $column, int $i, array &$joined, Builder $query): string|Expression
    {
        $table = $form->table_name;
        $alias = 'c'.$i;

        return match ($column->source_type) {
            'relation' => $this->relationSelect($form, $column, $alias, $joined, $query),

            'expression' => $this->expressionSelect($column, $alias),

            default => $table.'.'.$this->sources->assertColumn($table, $column->column_name).' as '.$alias,
        };
    }

    /**
     * Ekspresi bebas masuk langsung ke klausa SELECT, jadi dijaga dua lapis:
     * izin system.raw_query, lalu penyaringan SqlExpressionGuard.
     */
    private function expressionSelect(FormListColumn $column, string $alias): Expression
    {
        if (! auth()->user()?->hasPermission('system.raw_query')) {
            throw new \RuntimeException("Kolom ekspresi butuh izin 'system.raw_query'.");
        }

        $expression = $this->guard->assertSafe((string) $column->expression, "kolom '{$column->label}'");

        return DB::raw($expression.' as '.$alias);
    }

    private function relationSelect(Form $form, FormListColumn $column, string $alias, array &$joined, Builder $query): string
    {
        $relTable = $column->relation_table;
        $relKey = $column->relation_key;
        $relLabel = $column->relation_label;

        // Tabel relasi wajib punya izin baca sendiri — ikut whitelist yang sama.
        $this->sources->assertReadable($relTable);
        $this->sources->assertColumn($relTable, $relLabel);
        $this->sources->assertColumn($form->table_name, $column->column_name);

        $joinAlias = 'r_'.$alias;

        if (! in_array($joinAlias, $joined, true)) {
            $query->leftJoin(
                $relTable.' as '.$joinAlias,
                $joinAlias.'.'.$this->sources->assertColumn($relTable, $relKey),
                '=',
                $form->table_name.'.'.$column->column_name
            );
            $joined[] = $joinAlias;
        }

        return $joinAlias.'.'.$relLabel.' as '.$alias;
    }

    private function applySearch(Builder $query, Form $form, Collection $columns, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $q) use ($form, $columns, $search) {
            foreach ($columns as $column) {
                if (! $column->is_searchable || $column->source_type === 'expression') {
                    continue;
                }

                if ($column->source_type === 'relation') {
                    continue; // kolom hasil join dicari lewat kolom aslinya
                }

                $q->orWhere(
                    $form->table_name.'.'.$this->sources->assertColumn($form->table_name, $column->column_name),
                    'like',
                    '%'.$search.'%'
                );
            }
        });
    }

    private function applyOrder(Builder $query, Form $form, Collection $columns, array $params): void
    {
        $index = $params['order_column'] ?? null;
        $direction = ($params['order_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $column = $index !== null ? $columns->get((int) $index) : null;

        if ($column && $column->is_sortable && $column->source_type === 'column') {
            $query->orderBy(
                $form->table_name.'.'.$this->sources->assertColumn($form->table_name, $column->column_name),
                $direction
            );

            return;
        }

        if ($form->default_order_column) {
            $query->orderBy(
                $form->table_name.'.'.$this->sources->assertColumn($form->table_name, $form->default_order_column),
                $form->default_order_direction ?: 'asc'
            );

            return;
        }

        $query->orderBy($form->table_name.'.'.$form->primary_key, 'desc');
    }
}
