<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportJobController extends Controller
{
    public function index(Request $request): View
    {
        return view('exports.index', [
            'jobs' => ExportJob::where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    /** Status ringkas untuk penanda di pojok halaman. */
    public function status(Request $request): JsonResponse
    {
        $jobs = ExportJob::where('user_id', $request->user()->id)
            ->whereIn('status', ['queued', 'processing'])
            ->count();

        $done = ExportJob::where('user_id', $request->user()->id)
            ->where('status', 'done')
            ->where('finished_at', '>=', now()->subMinutes(10))
            ->count();

        return response()->json(['berjalan' => $jobs, 'selesai' => $done]);
    }

    public function download(Request $request, ExportJob $exportJob): StreamedResponse
    {
        // Berkas hasil ekspor hanya boleh diunduh pemesannya: isinya sudah
        // tersaring memakai izin dan cakupan data orang tersebut.
        abort_unless($exportJob->user_id === $request->user()->id, 403);
        abort_unless($exportJob->isDownloadable(), 404, 'Berkas belum siap.');
        abort_unless(Storage::disk(GenerateExport::DISK)->exists($exportJob->file_path), 404,
            'Berkas sudah tidak ada. Minta ekspor ulang.');

        return Storage::disk(GenerateExport::DISK)->download(
            $exportJob->file_path,
            \Illuminate\Support\Str::slug($exportJob->title).'.'.$exportJob->format
        );
    }

    public function destroy(Request $request, ExportJob $exportJob): RedirectResponse
    {
        abort_unless($exportJob->user_id === $request->user()->id, 403);

        if ($exportJob->file_path) {
            Storage::disk(GenerateExport::DISK)->delete($exportJob->file_path);
        }

        $exportJob->delete();

        return back()->with('success', 'Berkas ekspor dihapus.');
    }

    /**
     * Buang berkas ekspor milik sendiri yang lebih tua dari sekian hari.
     *
     * Pembersihan otomatis sudah terjadwal harian; ini untuk saat pengguna
     * ingin merapikan sendiri lebih cepat.
     */
    public function prune(Request $request): RedirectResponse
    {
        $days = (int) $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ])['days'];

        $jobs = ExportJob::where('user_id', $request->user()->id)
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        foreach ($jobs as $job) {
            if ($job->file_path) {
                Storage::disk(GenerateExport::DISK)->delete($job->file_path);
            }
        }

        ExportJob::whereIn('id', $jobs->pluck('id'))->delete();

        return back()->with('success',
            "{$jobs->count()} berkas ekspor lebih tua dari {$days} hari dibuang.");
    }

    /** Coba lagi ekspor yang gagal, dengan parameter yang sama. */
    public function retry(Request $request, ExportJob $exportJob): RedirectResponse
    {
        abort_unless($exportJob->user_id === $request->user()->id, 403);
        abort_unless($exportJob->status === 'failed', 400, 'Hanya ekspor gagal yang bisa diulang.');

        $exportJob->update([
            'status' => 'queued', 'error' => null,
            'started_at' => null, 'finished_at' => null,
        ]);

        GenerateExport::dispatch($exportJob->id);

        return back()->with('success', 'Ekspor diantrekan ulang.');
    }
}
