<?php

namespace App\Services\Report;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat definisi report dan pembersihan cache.
 *
 * Cerminan FormBuilderService: snapshot diambil SEBELUM perubahan disimpan,
 * sehingga versi terekam adalah keadaan yang bisa dikembalikan.
 */
class ReportBuilderService
{
    public function __construct(private readonly ReportService $reports) {}

    public function flush(Report $report): void
    {
        $this->reports->flush($report->code);

        // Opsi filter di-cache per id filter; tanpa dibuang, mengganti sumber
        // data filter tidak terlihat sampai 10 menit berikutnya.
        foreach (DB::table('report_filters')->where('report_id', $report->id)->pluck('id') as $filterId) {
            Cache::forget('report.filter.options.'.$filterId);
        }
    }

    public function snapshot(Report $report, ?User $user, ?string $note = null): int
    {
        $version = (int) DB::table('report_versions')->where('report_id', $report->id)->max('version') + 1;

        DB::table('report_versions')->insert([
            'report_id' => $report->id,
            'version' => $version,
            'snapshot' => json_encode($this->definition($report), JSON_UNESCAPED_UNICODE),
            'note' => $note,
            'created_by' => $user?->id,
            'created_at' => now(),
        ]);

        return $version;
    }

    /** Seluruh definisi report sebagai larik biasa. */
    public function definition(Report $report): array
    {
        return [
            'report' => DB::table('reports')->where('id', $report->id)->first(),
            'joins' => DB::table('report_joins')->where('report_id', $report->id)->orderBy('order_no')->get(),
            'columns' => DB::table('report_columns')->where('report_id', $report->id)->orderBy('order_no')->get(),
            'filters' => DB::table('report_filters')->where('report_id', $report->id)->orderBy('order_no')->get(),
        ];
    }

    /**
     * Kembalikan report ke versi tersimpan. Keadaan sekarang di-snapshot lebih
     * dulu supaya pemulihan sendiri pun bisa dibatalkan.
     */
    public function restore(Report $report, int $version, User $user): void
    {
        $row = DB::table('report_versions')
            ->where('report_id', $report->id)
            ->where('version', $version)
            ->first();

        abort_if($row === null, 404, "Versi {$version} tidak ditemukan.");

        $snapshot = json_decode($row->snapshot, true);

        DB::transaction(function () use ($report, $snapshot, $user, $version) {
            $this->snapshot($report, $user, "Sebelum dikembalikan ke versi {$version}");

            DB::table('report_joins')->where('report_id', $report->id)->delete();
            DB::table('report_columns')->where('report_id', $report->id)->delete();
            DB::table('report_filters')->where('report_id', $report->id)->delete();

            DB::table('reports')->where('id', $report->id)
                ->update(collect((array) $snapshot['report'])->except('id')->all());

            foreach (['joins' => 'report_joins', 'columns' => 'report_columns', 'filters' => 'report_filters'] as $key => $table) {
                foreach ($snapshot[$key] ?? [] as $row) {
                    DB::table($table)->insert(collect($row)->except('id')->all());
                }
            }
        });

        $this->flush($report);
    }
}
