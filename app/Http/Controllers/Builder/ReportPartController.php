<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\ReportColumnRequest;
use App\Http\Requests\Builder\ReportFilterRequest;
use App\Http\Requests\Builder\ReportJoinRequest;
use App\Models\Report;
use App\Models\ReportColumn;
use App\Models\ReportFilter;
use App\Models\ReportJoin;
use App\Services\Generator\TableInspector;
use App\Services\Report\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pengelola tiga bagian report: join, kolom, dan filter.
 *
 * Ketiganya berbagi pola yang sama (daftar, tambah, ubah, hapus, urutkan)
 * sehingga digabung di sini alih-alih tiga controller yang isinya kembar.
 */
class ReportPartController extends Controller
{
    public function __construct(
        private readonly ReportBuilderService $builder,
        private readonly ReportBuilderController $reports,
        private readonly TableInspector $inspector,
    ) {}

    // ---------------- JOIN ----------------

    public function joins(Report $report): View
    {
        return view('builder.reports.joins', [
            'report' => $report,
            'joins' => $report->joins,
            'tables' => $this->inspector->availableTables(),
            'references' => $this->reports->references($report),
        ]);
    }

    public function storeJoin(ReportJoinRequest $request, Report $report): RedirectResponse
    {
        $this->builder->snapshot($report, $request->user(), 'Sebelum tambah join');

        DB::table('report_joins')->insert([
            ...$request->safe()->all(),
            'report_id' => $report->id,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->builder->flush($report);

        return back()->with('success', 'Join berhasil ditambahkan.');
    }

    public function updateJoin(ReportJoinRequest $request, Report $report, ReportJoin $join): RedirectResponse
    {
        $this->assertOwned($report, $join->report_id);
        $this->builder->snapshot($report, $request->user(), "Sebelum ubah join {$join->alias()}");

        DB::table('report_joins')->where('id', $join->id)->update([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->builder->flush($report);

        return redirect()->route('builder.reports.joins.index', $report)
            ->with('success', 'Join berhasil diperbarui.');
    }

    public function destroyJoin(Request $request, Report $report, ReportJoin $join): RedirectResponse
    {
        $this->assertOwned($report, $join->report_id);

        // Kolom dan filter yang menunjuk alias ini akan gagal setelah join
        // dibuang, jadi pengguna diperingatkan alih-alih dibiarkan menemukan
        // sendiri saat report dibuka.
        $alias = $join->alias();
        $used = $report->columns->filter(fn ($c) => str_starts_with((string) $c->column_name, $alias.'.'))->count()
            + $report->filters->filter(fn ($f) => str_starts_with((string) $f->column_name, $alias.'.'))->count();

        if ($used > 0) {
            return back()->with('error',
                "Join '{$alias}' masih dipakai {$used} kolom/filter. Ubah atau hapus dulu yang memakainya.");
        }

        $this->builder->snapshot($report, $request->user(), "Sebelum hapus join {$alias}");
        DB::table('report_joins')->where('id', $join->id)->delete();
        $this->builder->flush($report);

        return back()->with('success', 'Join berhasil dihapus.');
    }

    // ---------------- KOLOM ----------------

    public function columns(Report $report): View
    {
        return view('builder.reports.columns', [
            'report' => $report,
            'columns' => $report->columns,
            'references' => $this->reports->references($report),
            'canExpression' => auth()->user()->hasPermission('system.raw_query'),
        ]);
    }

    public function storeColumn(ReportColumnRequest $request, Report $report): RedirectResponse
    {
        $this->builder->snapshot($report, $request->user(), 'Sebelum tambah kolom');

        DB::table('report_columns')->insert([
            ...$this->columnValues($request),
            'report_id' => $report->id,
        ]);

        $this->builder->flush($report);

        return back()->with('success', 'Kolom berhasil ditambahkan.');
    }

    public function updateColumn(ReportColumnRequest $request, Report $report, ReportColumn $column): RedirectResponse
    {
        $this->assertOwned($report, $column->report_id);
        $this->builder->snapshot($report, $request->user(), "Sebelum ubah kolom {$column->label}");

        DB::table('report_columns')->where('id', $column->id)->update($this->columnValues($request));
        $this->builder->flush($report);

        return redirect()->route('builder.reports.columns.index', $report)
            ->with('success', 'Kolom berhasil diperbarui.');
    }

    public function destroyColumn(Request $request, Report $report, ReportColumn $column): RedirectResponse
    {
        $this->assertOwned($report, $column->report_id);
        $this->builder->snapshot($report, $request->user(), "Sebelum hapus kolom {$column->label}");

        DB::table('report_columns')->where('id', $column->id)->delete();
        $this->builder->flush($report);

        return back()->with('success', 'Kolom berhasil dihapus.');
    }

    // ---------------- FILTER ----------------

    public function filters(Report $report): View
    {
        return view('builder.reports.filters', [
            'report' => $report,
            'filters' => $report->filters,
            'references' => $this->reports->references($report),
            'tables' => $this->inspector->availableTables(),
        ]);
    }

    public function storeFilter(ReportFilterRequest $request, Report $report): RedirectResponse
    {
        $this->builder->snapshot($report, $request->user(), 'Sebelum tambah filter');

        DB::table('report_filters')->insert([
            ...$this->filterValues($request),
            'report_id' => $report->id,
        ]);

        $this->builder->flush($report);

        return back()->with('success', 'Filter berhasil ditambahkan.');
    }

    public function updateFilter(ReportFilterRequest $request, Report $report, ReportFilter $filter): RedirectResponse
    {
        $this->assertOwned($report, $filter->report_id);
        $this->builder->snapshot($report, $request->user(), "Sebelum ubah filter {$filter->label}");

        DB::table('report_filters')->where('id', $filter->id)->update($this->filterValues($request));
        $this->builder->flush($report);

        return redirect()->route('builder.reports.filters.index', $report)
            ->with('success', 'Filter berhasil diperbarui.');
    }

    public function destroyFilter(Request $request, Report $report, ReportFilter $filter): RedirectResponse
    {
        $this->assertOwned($report, $filter->report_id);
        $this->builder->snapshot($report, $request->user(), "Sebelum hapus filter {$filter->label}");

        DB::table('report_filters')->where('id', $filter->id)->delete();
        $this->builder->flush($report);

        return back()->with('success', 'Filter berhasil dihapus.');
    }

    // ---------------- URUTAN ----------------

    public function reorder(Request $request, Report $report, string $part): JsonResponse
    {
        $table = match ($part) {
            'columns' => 'report_columns',
            'filters' => 'report_filters',
            'joins' => 'report_joins',
            default => abort(404),
        };

        $owned = DB::table($table)->where('report_id', $report->id)->pluck('id')->all();

        DB::transaction(function () use ($request, $table, $owned) {
            $order = 0;
            foreach ($request->input('order', []) as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DB::table($table)->where('id', $id)->update(['order_no' => ++$order]);
                }
            }
        });

        $this->builder->flush($report);

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function columnValues(ReportColumnRequest $request): array
    {
        $data = $request->safe()->all();

        // Kolom yang tak relevan dikosongkan agar sisa isian lama tidak terbawa.
        if ($data['source_type'] === 'expression') {
            $data['column_name'] = null;
        } else {
            $data['expression'] = null;
        }

        return [
            ...$data,
            'is_visible' => $request->boolean('is_visible'),
            'is_sortable' => $request->boolean('is_sortable'),
            'is_searchable' => $request->boolean('is_searchable'),
            'is_group_column' => $request->boolean('is_group_column'),
            'show_total' => $request->boolean('show_total'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function filterValues(ReportFilterRequest $request): array
    {
        $data = $request->safe()->except(['static_options_text', 'default_values_text']);

        if ($data['data_source_type'] !== 'table') {
            $data['data_source'] = $data['value_field'] = $data['label_field'] = null;
        }

        $defaults = $request->defaultValues();

        return [
            ...$data,
            'static_options' => ($options = $request->staticOptions()) ? json_encode($options) : null,
            'default_values' => $defaults ? json_encode($defaults) : null,
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function assertOwned(Report $report, int $reportId): void
    {
        abort_unless($reportId === $report->id, 404);
    }
}
