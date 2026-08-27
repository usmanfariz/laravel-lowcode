<?php

namespace App\Services\Form;

use App\Contracts\FormActionHandler;
use App\Contracts\FormHook;
use App\Models\Form;
use RuntimeException;

/**
 * Gerbang antara metadata dan kode Anda.
 *
 * Metadata hanya boleh menyebut kunci; class-nya diambil dari config. Ini
 * satu-satunya tempat kunci berubah jadi objek, jadi satu-satunya tempat yang
 * perlu diperiksa saat menanyakan "apa saja yang bisa dijalankan engine ini".
 */
class LowcodeRegistry
{
    /** @return array<string, class-string> kunci → class handler yang siap dipakai */
    public function handlers(): array
    {
        $siap = [];

        foreach ((array) config('lowcode.handlers', []) as $kunci => $class) {
            if ($this->alasanTidakSiap($class) === null) {
                $siap[$kunci] = $class;
            }
        }

        return $siap;
    }

    /**
     * Kunci terdaftar yang class-nya bermasalah, beserta alasannya.
     *
     * Tanpa ini, handler yang salah ketik hilang begitu saja dari daftar di
     * builder dan admin tidak punya petunjuk kenapa.
     *
     * @return array<string, string> kunci → alasan
     */
    public function invalidHandlers(): array
    {
        $rusak = [];

        foreach ((array) config('lowcode.handlers', []) as $kunci => $class) {
            if ($alasan = $this->alasanTidakSiap($class)) {
                $rusak[$kunci] = $alasan;
            }
        }

        return $rusak;
    }

    private function alasanTidakSiap(mixed $class): ?string
    {
        if (! is_string($class) || $class === '') {
            return 'nilainya bukan nama class';
        }

        if (! class_exists($class)) {
            return "class {$class} tidak ditemukan";
        }

        if (! is_subclass_of($class, FormActionHandler::class)) {
            return "class {$class} tidak mengimplementasikan ".FormActionHandler::class;
        }

        return null;
    }

    public function hasHandler(?string $key): bool
    {
        return $key !== null && array_key_exists($key, $this->handlers());
    }

    /**
     * Handler untuk sebuah kunci.
     *
     * Menolak kunci tak terdaftar dan class yang tidak memenuhi kontrak.
     * Keduanya berarti config dan metadata sudah tidak sejalan — lebih baik
     * gagal terang-terangan daripada menjalankan sesuatu yang tidak diduga.
     */
    public function handler(string $key): FormActionHandler
    {
        // Sengaja membaca config mentah, bukan handlers(): kunci yang terdaftar
        // tapi class-nya bermasalah harus melaporkan masalahnya, bukan mengaku
        // tidak terdaftar.
        $daftar = (array) config('lowcode.handlers', []);

        if (! array_key_exists($key, $daftar)) {
            throw new RuntimeException("Handler '{$key}' tidak terdaftar di config/lowcode.php.");
        }

        if ($alasan = $this->alasanTidakSiap($daftar[$key])) {
            throw new RuntimeException("Handler '{$key}' tidak bisa dipakai: {$alasan}.");
        }

        return app($daftar[$key]);
    }

    /**
     * Hook yang terpasang pada sebuah form, sesuai urutan pendaftarannya.
     *
     * @return array<int, FormHook>
     */
    public function hooksFor(Form $form): array
    {
        $classes = (array) (config('lowcode.hooks', [])[$form->code] ?? []);
        $hooks = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                throw new RuntimeException(
                    "Hook untuk form '{$form->code}' menunjuk class yang tidak ada: "
                    .var_export($class, true)
                );
            }

            $hook = app($class);

            if (! $hook instanceof FormHook) {
                throw new RuntimeException(
                    "Hook '{$class}' harus mengimplementasikan ".FormHook::class.'.'
                );
            }

            $hooks[] = $hook;
        }

        return $hooks;
    }

    /** @return array<int, class-string> untuk ditampilkan di builder, sekadar informasi */
    public function hookClassesFor(string $formCode): array
    {
        return array_values(array_filter(
            (array) (config('lowcode.hooks', [])[$formCode] ?? []),
            'is_string',
        ));
    }
}
