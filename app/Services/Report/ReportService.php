<?php

namespace App\Services\Report;

use App\Models\Report;
use App\Models\ReportFilter;
use App\Services\DataSourceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    public function __construct(private readonly DataSourceResolver $sources) {}

    public function byCode(string $code): Report
    {
        $id = Cache::rememberForever(
            "report.id.{$code}",
            fn () => Report::where('code', $code)->where('is_active', true)->value('id')
        );

        $report = $id ? Report::with(['joins', 'columns', 'filters'])->find($id) : null;

        if ($report === null || ! $report->is_active) {
            Cache::forget("report.id.{$code}");
            abort(404, "Report '{$code}' tidak ditemukan.");
        }

        $this->assertSourceAllowed($report);

        return $report;
    }

    /**
     * Report mode `raw` adalah titik paling berbahaya di seluruh sistem, jadi
     * dijaga tiga lapis: setting global, izin pemakai, dan validasi SELECT-only.
     */
    private function assertSourceAllowed(Report $report): void
    {
        if ($report->source_type !== 'raw') {
            $this->sources->assertReadable($report->base_table);

            return;
        }

        if (! setting('allow_raw_query', false)) {
            abort(403, 'Report mode raw dimatikan lewat pengaturan security.allow_raw_query.');
        }

        if (! auth()->user()?->hasPermission('system.raw_query')) {
            abort(403, "Report mode raw butuh izin 'system.raw_query'.");
        }

        if (! $this->isSelectOnly((string) $report->raw_query)) {
            abort(403, 'Raw query hanya boleh berupa SELECT tunggal.');
        }
    }

    /** Tolak apa pun selain satu pernyataan SELECT. */
    public function isSelectOnly(string $sql): bool
    {
        $normalized = trim($sql);

        if ($normalized === '' || ! preg_match('/^select\s/i', $normalized)) {
            return false;
        }

        // Titik koma di tengah membuka pintu pernyataan kedua.
        if (str_contains(rtrim($normalized, "; \t\n\r"), ';')) {
            return false;
        }

        $forbidden = [
            'insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
            'grant', 'revoke', 'replace', 'call', 'handler', 'load_file',
            'into\s+outfile', 'into\s+dumpfile', 'information_schema', 'mysql\.',
        ];

        foreach ($forbidden as $word) {
            if (preg_match('/\b'.$word.'\b/i', $normalized)) {
                return false;
            }
        }

        return true;
    }

    /** @return Collection<int, ReportFilter> */
    public function visibleFilters(Report $report): Collection
    {
        return $report->filters->where('is_active', true)->values();
    }

    public function flush(string $code): void
    {
        Cache::forget("report.id.{$code}");
    }
}
