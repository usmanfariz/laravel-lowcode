<?php

namespace App\Console\Commands;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Membuang berkas ekspor lama.
 *
 * Berkas ekspor menumpuk tanpa batas dan sebagian besar hanya diunduh sekali.
 * Tanpa pembersihan, direktori ini akan jadi yang terbesar di storage.
 */
class PruneExports extends Command
{
    protected $signature = 'exports:prune
                            {--days=7 : Buang ekspor yang lebih tua dari sekian hari}
                            {--dry-run : Tampilkan yang akan dibuang tanpa menghapusnya}';

    protected $description = 'Buang berkas ekspor yang sudah lewat masa simpan';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $jobs = ExportJob::where('created_at', '<', $cutoff)->get();

        if ($jobs->isEmpty()) {
            $this->info("Tidak ada ekspor yang lebih tua dari {$days} hari.");

            return self::SUCCESS;
        }

        $bytes = 0;
        $missing = 0;

        foreach ($jobs as $job) {
            if ($job->file_path && Storage::disk(GenerateExport::DISK)->exists($job->file_path)) {
                $bytes += (int) Storage::disk(GenerateExport::DISK)->size($job->file_path);

                if (! $dryRun) {
                    Storage::disk(GenerateExport::DISK)->delete($job->file_path);
                }
            } elseif ($job->file_path) {
                // Barisnya ada tapi berkasnya sudah tidak ada — tetap dibuang
                // supaya daftar tidak menyisakan tautan mati.
                $missing++;
            }
        }

        if (! $dryRun) {
            ExportJob::whereIn('id', $jobs->pluck('id'))->delete();
        }

        $this->info(sprintf(
            '%s%d ekspor (%s) lebih tua dari %d hari%s.',
            $dryRun ? '[uji coba] ' : '',
            $jobs->count(),
            $this->humanSize($bytes),
            $days,
            $dryRun ? ' akan dibuang' : ' dibuang'
        ));

        if ($missing > 0) {
            $this->warn("{$missing} baris tidak punya berkas fisik lagi.");
        }

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return $bytes < 1048576
            ? number_format($bytes / 1024, 0, ',', '.').' KB'
            : number_format($bytes / 1048576, 1, ',', '.').' MB';
    }
}
