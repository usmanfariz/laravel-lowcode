<?php

namespace App\Http\Controllers;

use App\Services\HelpBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint chatbot bantuan.
 *
 * Tanpa permission khusus: petunjuk cara memakai aplikasi berlaku untuk semua
 * yang bisa masuk. Yang dijaga hanyalah tombol tautan di dalam jawaban, yang
 * hanya muncul bila pengguna memang berhak membuka halamannya.
 */
class HelpBotController extends Controller
{
    public function __construct(private readonly HelpBot $bot) {}

    /** Topik untuk mode telusur, plus saran pembuka. */
    public function topics(): JsonResponse
    {
        return response()->json([
            'featured' => $this->bot->saranAwal(),
            'topics' => $this->bot->topik(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
        ], [], ['question' => 'pertanyaan']);

        $hasil = $this->bot->jawab($data['question'], $request->user());

        $this->bot->catat($hasil, $request->user());

        return response()->json($hasil);
    }

    /** Satu artikel utuh — dipakai saat pengguna menekan pertanyaan saran. */
    public function article(Request $request, int $id): JsonResponse
    {
        $artikel = $this->bot->artikel($id, $request->user());

        if ($artikel === null) {
            return response()->json(['message' => 'Artikel tidak ditemukan.'], 404);
        }

        return response()->json([
            'question' => $artikel['question'],
            'answered' => true,
            'answer' => $artikel,
            'related' => [],
            'score' => 0,
        ]);
    }
}
