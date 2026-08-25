<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ExportService;
use App\Services\Report\ReportChartBuilder;
use App\Services\Report\ReportFilterRenderer;
use App\Services\Report\ReportQueryBuilder;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportQueryBuilder $builder,
        private readonly ReportFilterRenderer $filters,
        private readonly ExportService $export,
        private readonly ReportChartBuilder $charts,
    ) {}

    /**
     * Ekspor seluruh hasil report — bukan hanya halaman yang tampil — dengan
     * filter yang sedang berlaku.
     */
    public function export(Request $request, string $code, string $format): Response
    {
        $report = $this->reports->byCode($code);
        $this->authorizeReport($request, $report);
        $this->authorizeFormat($report, $format);

        $columns = $report->columns->where('is_visible', true)->values();

        // Cetak selalu sinkron: hasilnya halaman, bukan berkas untuk diunduh.
        if ($format !== 'print' && ! $this->fitsSynchronously($request, $report)) {
            $job = $this->export->queue(
                'report', $report->code, $report->title ?: $report->name, $format,
                ['filters' => $this->filterInput($request), 'search' => $request->input('search')],
                $request->user(),
            );

            return redirect()->route('exports.index')->with('success',
                "Data terlalu banyak untuk diunduh langsung, jadi ekspor #{$job->id} "
                .'dikerjakan di latar belakang. Berkasnya muncul di halaman ini setelah selesai.');
        }

        try {
            [$rows, $totals] = $this->allRows($request, $report, $columns);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $title = $report->title ?: $report->name;
        $headings = $columns->pluck('label')->all();

        if ($format === 'print') {
            return response()->view('exports.print', compact('title', 'headings', 'rows', 'totals'));
        }

        return $this->export->download($format, $title, $headings, $rows, $title, $totals);
    }

    /**
     * Semua baris hasil report, sudah diformat sama seperti di layar.
     *
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, mixed>}
     */
    private function allRows(Request $request, Report $report, $columns): array
    {
        $query = $this->builder->base($report, $request->user());
        $this->builder->applyFilters($query, $report, $this->filterInput($request));
        $this->builder->applyGrouping($query, $report);

        $selects = [];
        foreach ($columns as $i => $column) {
            $selects[] = $this->builder->selectFor($report, $column, $i);
        }
        $query->select($selects);

        $this->builder->applySearch($query, $report, $request->input('search.value'));

        $this->export->assertWithinLimit(
            $this->countRows($query, $report),
            $report->export_queue_threshold
        );

        $this->builder->applyOrder($query, $report, null, 'asc');

        $raw = $query->get();
        $rows = [];

        foreach ($raw as $row) {
            $line = [];
            foreach ($columns as $i => $column) {
                $value = $this->format($row->{'c'.$i} ?? null, $column);
                // Boolean diubah jadi teks; berkas ekspor tidak punya badge.
                $line[] = is_bool($value) ? ($value ? 'Ya' : 'Tidak') : $value;
            }
            $rows[] = $line;
        }

        // Berbeda dari halaman list, total di sini dihitung dari SELURUH
        // dataset karena semua barisnya memang sudah ditarik.
        $totals = [];
        foreach ($columns as $i => $column) {
            if ($column->show_total) {
                $totals[$i] = $this->format(
                    $raw->sum(fn ($r) => (float) ($r->{'c'.$i} ?? 0)),
                    $column
                );
            }
        }

        return [$rows, $totals];
    }

    /** Hitung jumlah baris hasil filter untuk memutuskan sinkron atau antre. */
    private function fitsSynchronously(Request $request, Report $report): bool
    {
        $query = $this->builder->base($report, $request->user());
        $this->builder->applyFilters($query, $report, $this->filterInput($request));
        $this->builder->applyGrouping($query, $report);
        $this->builder->applySearch($query, $report, $request->input('search'));

        return $this->export->fitsSynchronously(
            $this->countRows($query, $report),
            $report->export_queue_threshold
        );
    }

    private function authorizeFormat(Report $report, string $format): void
    {
        $allowed = match ($format) {
            'xlsx' => $report->allow_export_excel,
            'pdf' => $report->allow_export_pdf,
            'csv' => $report->allow_export_csv,
            'print' => $report->allow_print,
            default => false,
        };

        abort_unless($allowed, 403, "Report ini tidak mengizinkan format '{$format}'.");
    }

    public function index(Request $request, string $code): View
    {
        $report = $this->reports->byCode($code);
        $this->authorizeReport($request, $report);

        return view('reports.index', [
            'report' => $report,
            'filters' => $this->reports->visibleFilters($report),
            'renderer' => $this->filters,
            'columns' => $report->columns->where('is_visible', true)->values(),
            // Alasan grafik tak bisa digambar ditampilkan apa adanya, supaya
            // pengguna tahu apa yang kurang alih-alih melihat kanvas kosong.
            'chartUnavailable' => $report->type === 'chart'
                ? $this->charts->reasonUnavailable($report)
                : null,
        ]);
    }

    /** Data grafik: label, deret nilai mentah, dan penanda bila terpotong. */
    public function chart(Request $request, string $code): JsonResponse
    {
        $report = $this->reports->byCode($code);
        $this->authorizeReport($request, $report);

        if ($alasan = $this->charts->reasonUnavailable($report)) {
            return response()->json(['error' => $alasan], 422);
        }

        try {
            return response()->json($this->charts->data(
                $report,
                $request->user(),
                $this->filterInput($request),
                $request->input('search.value') ?? $request->input('search'),
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Endpoint server-side DataTables. */
    public function data(Request $request, string $code): JsonResponse
    {
        $report = $this->reports->byCode($code);
        $this->authorizeReport($request, $report);

        $columns = $report->columns->where('is_visible', true)->values();

        try {
            $query = $this->builder->base($report, $request->user());

            $this->builder->applyFilters($query, $report, $this->filterInput($request));
            $this->builder->applyGrouping($query, $report);

            $selects = [];
            foreach ($columns as $i => $column) {
                $selects[] = $this->builder->selectFor($report, $column, $i);
            }
            $query->select($selects);

            $this->builder->applySearch($query, $report, $request->input('search.value'));

            // Report beragregat menghasilkan baris hasil GROUP BY, jadi
            // jumlahnya dihitung dari subquery — COUNT langsung akan
            // mengembalikan jumlah baris sebelum pengelompokan.
            $total = $this->countRows($query, $report);

            $this->builder->applyOrder(
                $query,
                $report,
                $request->filled('order.0.column') ? (int) $request->input('order.0.column') : null,
                (string) $request->input('order.0.dir', 'asc'),
            );

            $rows = $query
                ->skip(max(0, (int) $request->input('start', 0)))
                ->take(min(max(1, (int) $request->input('length', $report->per_page ?: setting('per_page', 25))), 500))
                ->get();
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $rows->map(function ($row) use ($columns) {
                $out = [];
                foreach ($columns as $i => $column) {
                    $out['c'.$i] = $this->format($row->{'c'.$i} ?? null, $column);
                }

                return $out;
            }),
            'totals' => $this->totals($rows, $columns),
        ]);
    }

    /**
     * Jumlah baris hasil report.
     *
     * Report beragregat dihitung lewat subquery karena COUNT langsung
     * mengembalikan jumlah baris SEBELUM pengelompokan.
     */
    private function countRows($query, Report $report): int
    {
        // Penentu grup diambil dari builder agar sama persis dengan yang
        // dipakai query-nya, termasuk pengelompokan otomatis.
        $hasGrouping = $this->builder->groupColumns($report)->isNotEmpty();

        if (! $hasGrouping) {
            // Report yang seluruh kolomnya agregat tanpa GROUP BY menghasilkan
            // tepat satu baris ringkasan. Menghitung baris sumbernya akan
            // melaporkan angka yang jauh lebih besar daripada yang tampil.
            $adaAgregat = $report->columns
                ->where('is_visible', true)
                ->contains(fn ($column) => $column->isAggregated());

            return $adaAgregat ? 1 : (clone $query)->getCountForPagination();
        }

        // Kolom subquery diganti konstanta: yang dihitung jumlah grup, bukan
        // isinya. Tanpa ini, query tanpa select eksplisit jatuh ke "select *"
        // dan report ber-join menghasilkan nama kolom ganda (dua kolom "id"),
        // yang ditolak MySQL sebagai derived table.
        $sub = (clone $query)->select(DB::raw('1'));

        return DB::query()->fromSub($sub, 'sub')->count();
    }

    /** Baris total untuk kolom yang ditandai show_total. */
    private function totals($rows, $columns): array
    {
        $totals = [];

        foreach ($columns as $i => $column) {
            if (! $column->show_total) {
                continue;
            }

            // Total dihitung dari halaman yang sedang tampil saja. Total
            // seluruh dataset butuh query terpisah — dikerjakan saat ekspor.
            $sum = $rows->sum(fn ($r) => (float) ($r->{'c'.$i} ?? 0));
            $totals['c'.$i] = $this->format($sum, $column);
        }

        return $totals;
    }

    private function format(mixed $value, $column): mixed
    {
        if ($value === null) {
            return null;
        }

        $decimals = $column->decimal_places ?? 2;

        return match ($column->format) {
            'number' => number_format((float) $value, 0, ',', '.'),
            'decimal' => number_format((float) $value, $decimals, ',', '.'),
            'currency' => 'Rp '.number_format((float) $value, $decimals, ',', '.'),
            'percentage' => number_format((float) $value, $decimals, ',', '.').'%',
            'date' => Carbon::parse($value)->format(setting('date_format', 'd/m/Y')),
            'datetime' => Carbon::parse($value)->format(setting('date_format', 'd/m/Y').' H:i'),
            'boolean' => (bool) $value,
            default => e((string) $value),
        };
    }

    /** @return array<int, array<int, mixed>> id filter → larik nilai */
    private function filterInput(Request $request): array
    {
        $input = [];

        foreach ((array) $request->input('f', []) as $id => $value) {
            $input[(int) $id] = is_array($value) ? array_values($value) : [$value];
        }

        return $input;
    }

    private function authorizeReport(Request $request, Report $report): void
    {
        if ($report->permission_code && ! $request->user()->hasPermission($report->permission_code)) {
            abort(403, "Anda tidak memiliki izin '{$report->permission_code}'.");
        }
    }
}
