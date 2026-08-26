<?php

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\Report;
use App\Models\User;
use App\Services\Report\ReportChartBuilder;
use App\Services\Report\ReportQueryBuilder;
use App\Support\ColumnFormatter;
use Illuminate\Support\Collection;

/**
 * Menyiapkan isi tiap widget dashboard.
 *
 * Widget bertipe `chart` dan `table` menumpang report yang sudah ada, jadi
 * seluruh whitelist, scope per baris, dan permission report-nya otomatis ikut
 * berlaku. Hanya widget `stat` yang punya query sendiri, dan itu pun tetap
 * lewat DataSourceResolver.
 */
class DashboardService
{
    public function __construct(
        private readonly DataSourceResolver $sources,
        private readonly ReportQueryBuilder $reports,
        private readonly ReportChartBuilder $charts,
    ) {}

    /** @return Collection<int, DashboardWidget> widget yang boleh dilihat user */
    public function widgetsFor(User $user): Collection
    {
        return DashboardWidget::where('is_active', true)
            ->orderBy('order_no')
            ->get()
            ->filter(fn (DashboardWidget $w) => $user->hasPermission($w->permission_code))
            ->values();
    }

    /**
     * Isi satu widget, sudah siap digambar.
     *
     * Satu widget yang bermasalah tidak boleh mengosongkan seluruh dashboard,
     * jadi kegagalannya dikembalikan sebagai pesan, bukan exception.
     *
     * @return array<string, mixed>
     */
    public function resolve(DashboardWidget $widget, User $user): array
    {
        try {
            return match ($widget->type) {
                'stat' => ['value' => $this->statValue($widget)],
                'chart' => $this->chartData($widget, $user),
                'table' => $this->tableData($widget, $user),
                default => [],
            };
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------

    private function statValue(DashboardWidget $widget): float|int
    {
        $table = (string) $widget->source_table;

        // Nama tabel dan kolom berasal dari metadata, jadi tetap lewat whitelist.
        $query = $this->sources->query($table);

        // Baris terhapus tidak ikut dihitung bila tabelnya memang punya penanda.
        if (in_array('deleted_at', $this->sources->allowedColumns($table), true)) {
            $query->whereNull('deleted_at');
        }

        foreach ($widget->filter ?? [] as $column => $value) {
            $this->sources->assertColumn($table, (string) $column);

            is_array($value)
                ? $query->whereIn($column, $value)
                : $query->where($column, $value);
        }

        if ($widget->aggregate === 'count') {
            return (int) $query->count();
        }

        $column = $this->sources->assertColumn($table, (string) $widget->source_column);

        return (float) ($query->{$widget->aggregate}($column) ?? 0);
    }

    /** @return array<string, mixed> */
    private function chartData(DashboardWidget $widget, User $user): array
    {
        $report = $this->report($widget, $user);

        if ($reason = $this->charts->reasonUnavailable($report)) {
            return ['error' => $reason];
        }

        return [
            'report' => $report,
            'chart' => $this->charts->data($report, $user),
        ];
    }

    /** @return array<string, mixed> */
    private function tableData(DashboardWidget $widget, User $user): array
    {
        $report = $this->report($widget, $user);
        $columns = $report->columns->where('is_visible', true)->values();

        $query = $this->reports->base($report, $user);
        $this->reports->applyFilters($query, $report, []);
        $this->reports->applyGrouping($query, $report);

        $selects = [];
        foreach ($columns as $i => $column) {
            $selects[] = $this->reports->selectFor($report, $column, $i);
        }
        $query->select($selects);
        $this->reports->applyOrder($query, $report, null, 'asc');

        $limit = max(1, min((int) ($widget->row_limit ?: 5), 50));

        // Nilai diformat di sini, bukan di view: aturannya harus sama persis
        // dengan halaman report-nya. Mode plain dipakai karena Blade sudah
        // meng-escape sendiri lewat `{{ }}`.
        $rows = $query->limit($limit)->get()->map(function ($row) use ($columns) {
            $cells = [];

            foreach ($columns as $i => $column) {
                $cells[$i] = ColumnFormatter::plain($row->{'c'.$i} ?? null, $column);
            }

            return $cells;
        });

        return [
            'report' => $report,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Report yang ditumpangi widget, sudah diperiksa izinnya.
     *
     * Widget tidak boleh jadi jalan pintas melewati permission report: siapa
     * pun yang tidak berhak membuka report-nya juga tidak berhak melihat
     * angkanya di dashboard.
     */
    private function report(DashboardWidget $widget, User $user): Report
    {
        $report = Report::with(['joins', 'columns', 'filters'])
            ->where('code', $widget->report_code)
            ->where('is_active', true)
            ->first();

        if ($report === null) {
            throw new \RuntimeException("Report '{$widget->report_code}' tidak ditemukan atau nonaktif.");
        }

        if ($report->permission_code && ! $user->hasPermission($report->permission_code)) {
            throw new \RuntimeException("Anda tidak memiliki izin '{$report->permission_code}'.");
        }

        return $report;
    }

    /** Format angka widget stat sesuai setelannya. */
    public function formatValue(DashboardWidget $widget, float|int $value): string
    {
        return match ($widget->format) {
            'decimal' => number_format((float) $value, 2, ',', '.'),
            'currency' => 'Rp '.number_format((float) $value, 0, ',', '.'),
            'percentage' => number_format((float) $value, 1, ',', '.').'%',
            default => number_format((float) $value, 0, ',', '.'),
        };
    }
}
