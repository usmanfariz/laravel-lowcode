<?php

namespace App\Services;

use App\Exports\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengubah tabel (judul kolom + baris) menjadi berkas unduhan.
 *
 * Nilai yang masuk sudah diformat oleh pemanggil, jadi isi berkas identik
 * dengan yang tampil di layar.
 */
class ExportService
{
    /** Batas keras, terlepas dari export_queue_threshold tiap report. */
    public const HARD_LIMIT = 50000;

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function download(
        string $format,
        string $filename,
        array $headings,
        array $rows,
        string $title = 'Data',
        array $totals = [],
    ): Response|StreamedResponse {
        $slug = Str::slug($filename) ?: 'export';

        return match ($format) {
            'xlsx' => Excel::download(
                new TabularExport($headings, $this->withTotals($rows, $totals, $headings), $title),
                $slug.'.xlsx'
            ),

            'csv' => $this->csv($slug, $headings, $this->withTotals($rows, $totals, $headings)),

            'pdf' => Pdf::loadView('exports.pdf', [
                'title' => $title,
                'headings' => $headings,
                'rows' => $rows,
                'totals' => $totals,
            ])
                // Kolom report gampang melebihi lebar potret.
                ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')
                ->download($slug.'.pdf'),

            default => throw new RuntimeException("Format ekspor '{$format}' tidak dikenal."),
        };
    }

    /**
     * CSV ditulis streaming, bukan dirakit di memori — dataset besar tidak
     * boleh menahan seluruh berkas sekaligus.
     */
    private function csv(string $slug, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM agar Excel di Windows membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headings);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $slug.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Sisipkan baris total di akhir bila ada. */
    private function withTotals(array $rows, array $totals, array $headings): array
    {
        if ($totals === []) {
            return $rows;
        }

        $line = array_fill(0, count($headings), '');
        $line[0] = 'TOTAL';

        foreach ($totals as $index => $value) {
            if (isset($line[$index])) {
                $line[$index] = $value;
            }
        }

        $rows[] = $line;

        return $rows;
    }

    /** Apakah jumlah baris ini masih wajar dikerjakan sinkron. */
    public function fitsSynchronously(int $rowCount, ?int $threshold): bool
    {
        return $rowCount <= min($threshold ?: self::HARD_LIMIT, self::HARD_LIMIT);
    }

    /**
     * Ekspor sinkron menahan seluruh proses web, jadi di atas ambang
     * pekerjaannya dipindah ke antrean.
     */
    public function assertWithinLimit(int $rowCount, ?int $threshold): void
    {
        if (! $this->fitsSynchronously($rowCount, $threshold)) {
            $limit = min($threshold ?: self::HARD_LIMIT, self::HARD_LIMIT);

            throw new RuntimeException(
                "Data terlalu banyak untuk diekspor langsung ({$rowCount} baris, batas {$limit})."
            );
        }
    }

    /**
     * Antrekan ekspor dan kembalikan barisnya.
     *
     * @param  array<string, mixed>  $params  filter dan pencarian yang berlaku
     */
    public function queue(
        string $sourceType,
        string $sourceCode,
        string $title,
        string $format,
        array $params,
        \App\Models\User $user,
    ): \App\Models\ExportJob {
        $job = \App\Models\ExportJob::create([
            'user_id' => $user->id,
            'source_type' => $sourceType,
            'source_code' => $sourceCode,
            'title' => $title,
            'format' => $format,
            'params' => $params,
            'status' => 'queued',
        ]);

        \App\Jobs\GenerateExport::dispatch($job->id);

        return $job;
    }
}
