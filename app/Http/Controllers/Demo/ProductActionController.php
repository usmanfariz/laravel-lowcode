<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\DataSourceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CONTOH — bukan bagian dari engine.
 *
 * Tombol aksi (`form_actions`) sengaja tidak diurus engine: apa arti "setujui"
 * atau "arsipkan" adalah urusan aplikasi, bukan urusan generator. Controller
 * ini memperlihatkan bagaimana aksi semacam itu dipasang, dan menjadi contoh
 * yang bisa dicoba dari form demo `product`.
 *
 * Aman dihapus bersama migrasi dan seeder demo lainnya.
 */
class ProductActionController extends Controller
{
    public function __construct(
        private readonly DataSourceResolver $sources,
        private readonly ActivityLogger $log,
    ) {}

    /** Aksi per baris: ubah status satu produk menjadi published. */
    public function approve(Request $request): JsonResponse
    {
        return $this->ubahStatus($request, 'published', 'disetujui');
    }

    /** Aksi massal: arsipkan seluruh baris yang dicentang. */
    public function archive(Request $request): JsonResponse
    {
        return $this->ubahStatus($request, 'archived', 'diarsipkan');
    }

    /** Aksi toolbar: halaman siap cetak berisi label produk. */
    public function printLabel(Request $request): View
    {
        $this->sources->assertReadable('demo_products');

        $ids = array_filter((array) $request->input('ids', []));

        $query = $this->sources->query('demo_products')
            ->select(['id', 'code', 'name', 'price'])
            ->whereNull('deleted_at')
            ->orderBy('code');

        // Tanpa pilihan baris, seluruh produk aktif dicetak.
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        return view('demo.product-labels', ['items' => $query->limit(500)->get()]);
    }

    private function ubahStatus(Request $request, string $status, string $kata): JsonResponse
    {
        // Aksi ini menulis, jadi izin tulisnya diperiksa seperti jalur lain.
        $this->sources->assertWritable('demo_products');

        $ids = array_values(array_filter((array) $request->input('ids', [])));

        if ($ids === []) {
            return response()->json(['message' => 'Tidak ada baris yang dipilih.'], 422);
        }

        $before = $this->sources->query('demo_products')
            ->whereIn('id', $ids)->pluck('status', 'id');

        $affected = DB::transaction(fn () => $this->sources->query('demo_products')
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->update(['status' => $status, 'updated_at' => now()]));

        foreach ($ids as $id) {
            $this->log->record('update', 'demo_products', $id,
                ['status' => $before[$id] ?? null], ['status' => $status], 'aksi.'.$kata);
        }

        return response()->json([
            'message' => "{$affected} produk {$kata}.",
            'affected' => $affected,
        ]);
    }
}
