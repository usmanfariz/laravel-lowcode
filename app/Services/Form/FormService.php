<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Services\DataSourceResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Pemuat definisi form.
 *
 * Satu halaman form memuat 4–5 tabel metadata, jadi hasilnya di-cache per kode
 * form. Cache dihapus lewat flush() saat builder menyimpan.
 */
class FormService
{
    public function __construct(private readonly DataSourceResolver $sources) {}

    public function byCode(string $code): Form
    {
        $id = Cache::rememberForever(
            $this->cacheKey($code),
            fn () => Form::where('code', $code)->where('is_active', true)->value('id')
        );

        if ($id === null) {
            // Jangan biarkan kode tak dikenal mengendap di cache sebagai null.
            Cache::forget($this->cacheKey($code));
            abort(404, "Form '{$code}' tidak ditemukan.");
        }

        $form = Form::with([
            'fields.options',
            'details.fields.options',
            'actions',
        ])->find($id);

        if ($form === null || ! $form->is_active) {
            Cache::forget($this->cacheKey($code));
            abort(404, "Form '{$code}' tidak ditemukan.");
        }

        // Tabel target diperiksa di sini, sekali, sebelum metadata dipakai
        // renderer maupun engine CRUD.
        $this->sources->assertReadable($form->table_name);

        foreach ($form->details as $detail) {
            $this->sources->assertReadable($detail->table_name);
        }

        return $form;
    }

    public function flush(string $code): void
    {
        Cache::forget($this->cacheKey($code));
    }

    private function cacheKey(string $code): string
    {
        return "form.id.{$code}";
    }
}
