<?php

namespace App\Services\Report;

use App\Models\Report;
use App\Models\ReportColumn;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menyiapkan data grafik dari definisi report yang sudah ada.
 *
 * Tidak ada metadata terpisah untuk grafik selain bentuk dan batas barisnya:
 * label diambil dari kolom pengelompokan, dan deret nilainya dari kolom
 * beragregat. Report yang sudah masuk akal sebagai ringkasan otomatis masuk
 * akal pula sebagai grafik.
 */
class ReportChartBuilder
{
    /** Format kolom yang nilainya memang angka. */
    private const NUMERIC = ['number', 'decimal', 'currency', 'percentage'];

    /** Warna deret; berulang bila deretnya lebih banyak. */
    private const COLORS = [
        '#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6f42c1',
        '#20c997', '#d63384', '#0dcaf0', '#ffc107', '#6610f2',
    ];

    public function __construct(private readonly ReportQueryBuilder $builder) {}

    /** Kolom yang jadi label sumbu — kolom pengelompokan, atau non-agregat pertama. */
    public function labelColumn(Report $report): ?ReportColumn
    {
        $group = $this->builder->groupColumns($report);

        if ($group->isNotEmpty()) {
            return $group->first();
        }

        return $report->columns
            ->where('is_visible', true)
            ->reject(fn (ReportColumn $c) => $c->isAggregated())
            ->first();
    }

    /** @return Collection<int, ReportColumn> kolom angka yang jadi deret nilai */
    public function valueColumns(Report $report): Collection
    {
        $label = $this->labelColumn($report);

        return $report->columns
            ->where('is_visible', true)
            ->reject(fn (ReportColumn $c) => $label && $c->id === $label->id)
            ->filter(fn (ReportColumn $c) => in_array($c->format, self::NUMERIC, true))
            ->values();
    }

    /**
     * Alasan report ini belum bisa digambar, atau null bila sudah bisa.
     *
     * Dikembalikan sebagai kalimat agar halaman bisa menjelaskan apa yang
     * kurang, bukan menampilkan kanvas kosong.
     */
    public function reasonUnavailable(Report $report): ?string
    {
        if ($report->source_type === 'raw') {
            return 'Report mode raw belum bisa digambar sebagai grafik.';
        }

        if ($this->labelColumn($report) === null) {
            return 'Grafik butuh satu kolom sebagai label — tandai satu kolom '
                .'sebagai kolom pengelompokan, atau tambahkan kolom non-agregat.';
        }

        if ($this->valueColumns($report)->isEmpty()) {
            return 'Grafik butuh minimal satu kolom angka. Setel format kolom '
                .'nilainya ke Angka, Desimal, Mata uang, atau Persen.';
        }

        return null;
    }

    /**
     * Data siap pakai untuk Chart.js.
     *
     * @param  array<int, array<int, mixed>>  $filters
     * @return array{labels: array, datasets: array, truncated: bool, total: int}
     */
    public function data(Report $report, User $user, array $filters = [], ?string $search = null): array
    {
        $label = $this->labelColumn($report);
        $values = $this->valueColumns($report);

        $columns = $report->columns->where('is_visible', true)->values();

        $query = $this->builder->base($report, $user);
        $this->builder->applyFilters($query, $report, $filters);
        $this->builder->applyGrouping($query, $report);

        $selects = [];
        foreach ($columns as $i => $column) {
            $selects[] = $this->builder->selectFor($report, $column, $i);
        }
        $query->select($selects);

        $this->builder->applySearch($query, $report, $search);
        $this->builder->applyOrder($query, $report, null, 'asc');

        $limit = max(1, min((int) ($report->chart_limit ?: 30), 200));

        // Satu baris lebih diambil untuk tahu apakah datanya terpotong.
        $rows = $query->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $indexOf = fn (ReportColumn $c) => $columns->search(fn ($x) => $x->id === $c->id);

        $labelIndex = $label ? $indexOf($label) : null;

        return [
            'labels' => $rows->map(fn ($row) => $labelIndex === null
                ? ''
                : (string) ($row->{'c'.$labelIndex} ?? '—'))->all(),

            'datasets' => $values->values()->map(function (ReportColumn $column, int $i) use ($rows, $indexOf) {
                $index = $indexOf($column);
                $color = self::COLORS[$i % count(self::COLORS)];

                return [
                    'label' => $column->label,
                    'format' => $column->format,
                    'color' => $color,
                    // Nilai mentah, bukan yang sudah diformat: "Rp 12.500,00"
                    // tidak bisa digambar.
                    'data' => $rows->map(fn ($row) => (float) ($row->{'c'.$index} ?? 0))->all(),
                ];
            })->all(),

            'truncated' => $truncated,
            'total' => $rows->count(),
        ];
    }
}
