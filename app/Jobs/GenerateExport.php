<?php

namespace App\Jobs;

use App\Exports\TabularExport;
use App\Models\ExportJob;
use App\Models\Form;
use App\Models\Report;
use App\Services\ExportService;
use App\Services\Form\FormQueryBuilder;
use App\Services\Report\ReportFilterRenderer;
use App\Services\Report\ReportQueryBuilder;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Menyusun berkas ekspor di latar belakang.
 *
 * Dijalankan queue worker, bukan request web — ekspor besar bisa memakan
 * menit dan pasti melewati batas waktu request.
 */
class GenerateExport implements ShouldQueue
{
    use Queueable;

    /** Berkas disimpan di disk privat; unduhan tetap lewat route berizin. */
    public const DISK = 'local';

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $exportJobId) {}

    public function handle(): void
    {
        $job = ExportJob::find($this->exportJobId);

        if ($job === null || $job->status !== 'queued') {
            return;
        }

        $job->update(['status' => 'processing', 'started_at' => now()]);

        try {
            [$headings, $rows, $totals] = $job->source_type === 'report'
                ? $this->reportData($job)
                : $this->formData($job);

            $path = $this->write($job, $headings, $rows, $totals);

            $job->update([
                'status' => 'done',
                'row_count' => count($rows),
                'file_path' => $path,
                'file_size' => Storage::disk(self::DISK)->size($path),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Pesan disimpan agar pengguna tahu sebabnya tanpa membuka log.
            $job->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);

            report($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        ExportJob::where('id', $this->exportJobId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'finished_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------

    /** @return array{0: array, 1: array, 2: array} */
    private function formData(ExportJob $job): array
    {
        $form = Form::where('code', $job->source_code)->firstOrFail();
        $builder = app(FormQueryBuilder::class);
        $columns = $builder->columns($form);

        $result = $builder->paginate($form, $job->user, [
            'search' => $job->params['search'] ?? null,
            'start' => 0,
            'length' => ExportService::HARD_LIMIT,
        ]);

        $rows = [];
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach ($columns as $i => $column) {
                $line[] = $this->plain($row->{'c'.$i} ?? null, $column);
            }
            $rows[] = $line;
        }

        return [$columns->pluck('label')->all(), $rows, []];
    }

    /** @return array{0: array, 1: array, 2: array} */
    private function reportData(ExportJob $job): array
    {
        $report = Report::where('code', $job->source_code)->firstOrFail();
        $builder = app(ReportQueryBuilder::class);
        $columns = $report->columns->where('is_visible', true)->values();

        // Izin pemesan diterapkan ulang di worker: scope per baris ikut
        // berlaku, sehingga berkas hasil tidak pernah memuat baris yang tidak
        // boleh dilihat pemesannya.
        $query = $builder->base($report, $job->user);
        $builder->applyFilters($query, $report, $job->params['filters'] ?? []);
        $builder->applyGrouping($query, $report);

        $selects = [];
        foreach ($columns as $i => $column) {
            $selects[] = $builder->selectFor($report, $column, $i);
        }
        $query->select($selects);

        $builder->applySearch($query, $report, $job->params['search'] ?? null);
        $builder->applyOrder($query, $report, null, 'asc');

        $raw = $query->limit(ExportService::HARD_LIMIT)->get();

        $rows = [];
        foreach ($raw as $row) {
            $line = [];
            foreach ($columns as $i => $column) {
                $line[] = $this->plain($row->{'c'.$i} ?? null, $column);
            }
            $rows[] = $line;
        }

        $totals = [];
        foreach ($columns as $i => $column) {
            if ($column->show_total) {
                $totals[$i] = $this->plain($raw->sum(fn ($r) => (float) ($r->{'c'.$i} ?? 0)), $column);
            }
        }

        return [$columns->pluck('label')->all(), $rows, $totals];
    }

    private function write(ExportJob $job, array $headings, array $rows, array $totals): string
    {
        $name = 'exports/'.$job->id.'-'.\Illuminate\Support\Str::slug($job->title).'.'.$job->format;

        if ($totals !== []) {
            $line = array_fill(0, count($headings), '');
            $line[0] = 'TOTAL';
            foreach ($totals as $i => $value) {
                if (isset($line[$i])) {
                    $line[$i] = $value;
                }
            }
            $rows[] = $line;
        }

        match ($job->format) {
            'xlsx' => Excel::store(new TabularExport($headings, $rows, $job->title), $name, self::DISK),

            'csv' => Storage::disk(self::DISK)->put($name, $this->csv($headings, $rows)),

            'pdf' => Storage::disk(self::DISK)->put($name, Pdf::loadView('exports.pdf', [
                'title' => $job->title, 'headings' => $headings,
                'rows' => $rows, 'totals' => [],
            ])->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')->output()),

            default => throw new \RuntimeException("Format '{$job->format}' tidak dikenal."),
        };

        return $name;
    }

    private function csv(array $headings, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headings);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /** Format tampilan yang sama dengan layar, tanpa penanda HTML. */
    private function plain(mixed $value, $column): mixed
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
            'date' => \Carbon\Carbon::parse($value)->format('d/m/Y'),
            'datetime' => \Carbon\Carbon::parse($value)->format('d/m/Y H:i'),
            'boolean' => $value ? 'Ya' : 'Tidak',
            default => (string) $value,
        };
    }
}
