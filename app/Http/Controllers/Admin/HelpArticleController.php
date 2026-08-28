<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HelpArticleRequest;
use App\Models\HelpArticle;
use App\Models\HelpQuery;
use App\Models\Permission;
use App\Services\HelpBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kelola basis pengetahuan chatbot bantuan.
 *
 * Halaman ini punya dua sisi: daftar artikel, dan daftar pertanyaan yang tidak
 * terjawab. Yang kedua itulah yang membuat basis pengetahuan lokal bisa
 * mengejar aplikasinya — tanpa daftar itu, tidak ada yang tahu jawaban apa
 * yang masih kurang sampai ada yang mengeluh.
 */
class HelpArticleController extends Controller
{
    public function __construct(private readonly HelpBot $bot) {}

    public function index(Request $request): View
    {
        $artikel = HelpArticle::query()
            ->when($request->filled('kategori'), fn ($q) => $q->where('category', $request->input('kategori')))
            ->when($request->filled('cari'), function ($q) use ($request) {
                $cari = $request->input('cari');
                $q->where(fn ($w) => $w->where('question', 'like', "%{$cari}%")
                    ->orWhere('keywords', 'like', "%{$cari}%")
                    ->orWhere('answer', 'like', "%{$cari}%"));
            })
            ->orderBy('category')
            ->orderBy('order_no')
            ->orderBy('id')
            ->get();

        return view('admin.help.index', [
            'artikel' => $artikel->groupBy('category'),
            'kategori' => HelpArticle::distinct()->orderBy('category')->pluck('category'),
            'jumlah' => HelpArticle::count(),
            'filter' => $request->only(['kategori', 'cari']),
            'takTerjawab' => $this->takTerjawab(),
        ]);
    }

    public function create(): View
    {
        return view('admin.help.form', [
            'article' => new HelpArticle([
                'is_active' => true, 'is_featured' => false, 'order_no' => 0,
                'category' => request('kategori', 'Umum'),
                'question' => request('pertanyaan', ''),
            ]),
            'kategori' => HelpArticle::distinct()->orderBy('category')->pluck('category'),
            'permissions' => Permission::orderBy('code')->pluck('code'),
        ]);
    }

    public function store(HelpArticleRequest $request): RedirectResponse
    {
        HelpArticle::create($this->nilai($request));
        $this->bot->flush();

        return redirect()->route('help-articles.index')
            ->with('success', 'Artikel bantuan berhasil ditambahkan.');
    }

    public function edit(HelpArticle $helpArticle): View
    {
        return view('admin.help.form', [
            'article' => $helpArticle,
            'kategori' => HelpArticle::distinct()->orderBy('category')->pluck('category'),
            'permissions' => Permission::orderBy('code')->pluck('code'),
        ]);
    }

    public function update(HelpArticleRequest $request, HelpArticle $helpArticle): RedirectResponse
    {
        $helpArticle->update($this->nilai($request));
        $this->bot->flush();

        return redirect()->route('help-articles.index')
            ->with('success', 'Artikel bantuan berhasil diperbarui.');
    }

    public function destroy(HelpArticle $helpArticle): RedirectResponse
    {
        $helpArticle->delete();
        $this->bot->flush();

        return redirect()->route('help-articles.index')
            ->with('success', 'Artikel bantuan berhasil dihapus.');
    }

    /** Buang riwayat pertanyaan yang sudah lewat. */
    public function prune(Request $request): RedirectResponse
    {
        $hari = (int) $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [], ['days' => 'jumlah hari'])['days'];

        $dibuang = HelpQuery::where('created_at', '<', now()->subDays($hari))->delete();

        return back()->with('success',
            "{$dibuang} riwayat pertanyaan lebih tua dari {$hari} hari dihapus.");
    }

    private function nilai(HelpArticleRequest $request): array
    {
        return [
            ...$request->safe()->all(),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Pertanyaan tanpa jawaban, dikelompokkan menurut bunyinya.
     *
     * Dikelompokkan supaya lima orang yang menanyakan hal sama tampil sebagai
     * satu baris dengan angka lima — bukan lima baris yang menenggelamkan
     * pertanyaan lain.
     */
    private function takTerjawab()
    {
        return HelpQuery::query()
            ->selectRaw('MIN(id) as id, question, COUNT(*) as jumlah, MAX(created_at) as terakhir')
            ->where('is_answered', false)
            ->groupBy('question')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit(20)
            ->get();
    }
}
