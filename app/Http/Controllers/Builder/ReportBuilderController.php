<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\ReportSettingRequest;
use App\Models\Report;
use App\Services\DataSourceResolver;
use App\Services\Generator\TableInspector;
use App\Services\Report\ReportBuilderService;
use App\Support\DropsNullDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportBuilderController extends Controller
{
    use DropsNullDefaults;

    public function __construct(
        private readonly ReportBuilderService $builder,
        private readonly TableInspector $inspector,
        private readonly DataSourceResolver $sources,
    ) {}

    public function index(): View
    {
        return view('builder.reports.index', [
            'reports' => Report::withCount(['columns', 'filters', 'joins'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('builder.reports.create', [
            'tables' => $this->inspector->availableTables(),
        ]);
    }

    public function store(ReportSettingRequest $request): RedirectResponse
    {
        $report = Report::create([
            ...$this->values($request),
            'connection' => 'mysql',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('builder.reports.columns.index', $report)
            ->with('success', "Report '{$report->name}' dibuat. Tambahkan kolomnya sekarang.");
    }

    public function edit(Report $report): View
    {
        return view('builder.reports.edit', [
            'report' => $report,
            'references' => $this->references($report),
            'versions' => DB::table('report_versions')
                ->where('report_id', $report->id)
                ->orderByDesc('version')
                ->limit(20)
                ->get(),
        ]);
    }

    public function restore(Request $request, Report $report, int $version): RedirectResponse
    {
        $this->builder->restore($report, $version, $request->user());

        return back()->with('success', "Definisi report dikembalikan ke versi {$version}.");
    }

    public function update(ReportSettingRequest $request, Report $report): RedirectResponse
    {
        // Snapshot diambil sebelum perubahan, sehingga versi terekam adalah
        // keadaan yang bisa dikembalikan.
        $this->builder->snapshot($report, $request->user(), $request->input('note'));

        $report->update([
            ...$this->values($request),
            'updated_by' => $request->user()->id,
        ]);

        $this->builder->flush($report);

        return back()->with('success', 'Pengaturan report berhasil disimpan.');
    }

    public function destroy(Report $report): RedirectResponse
    {
        $code = $report->code;

        DB::transaction(function () use ($report) {
            DB::table('report_joins')->where('report_id', $report->id)->delete();
            DB::table('report_columns')->where('report_id', $report->id)->delete();
            DB::table('report_filters')->where('report_id', $report->id)->delete();
            DB::table('menus')->where('link_type', 'report')->where('target_value', $report->code)->delete();
            $report->delete();
        });

        $this->builder->flush($report);
        app(\App\Services\MenuService::class)->flush();

        return redirect()->route('builder.reports.index')
            ->with('success', "Report '{$code}' dihapus. Tabel sumbernya tidak disentuh.");
    }

    private function values(ReportSettingRequest $request): array
    {
        return $this->dropNullDefaults([
            ...$request->safe()->except('note'),
            'use_soft_delete' => $request->boolean('use_soft_delete'),
            'allow_export_excel' => $request->boolean('allow_export_excel'),
            'allow_export_pdf' => $request->boolean('allow_export_pdf'),
            'allow_export_csv' => $request->boolean('allow_export_csv'),
            'allow_print' => $request->boolean('allow_print'),
            'is_active' => $request->boolean('is_active'),
        ], ['export_queue_threshold']);
    }

    /**
     * Seluruh referensi kolom yang sah untuk report ini, dalam bentuk
     * "alias.kolom" — dipakai dropdown di form kolom dan filter.
     *
     * @return array<string, array<int, string>>
     */
    public function references(Report $report): array
    {
        $out = [];

        $tables = [$report->alias() => $report->base_table];
        foreach ($report->joins as $join) {
            $tables[$join->alias()] = $join->table_name;
        }

        foreach ($tables as $alias => $table) {
            try {
                $out[$alias.' ('.$table.')'] = array_map(
                    fn ($column) => $alias.'.'.$column,
                    $this->sources->allowedColumns($table)
                );
            } catch (\Throwable) {
                // Tabel yang dicabut dari whitelist tidak menghentikan halaman;
                // referensinya sekadar tidak ditawarkan.
                continue;
            }
        }

        return $out;
    }
}
