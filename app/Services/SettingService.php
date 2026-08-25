<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembacaan dan penyimpanan pengaturan aplikasi.
 *
 * Halaman Pengaturan digambar dari isi tabel `settings`, jadi service ini
 * hanya mengurus baris yang memang terdaftar di sana. Kunci asing dari request
 * diabaikan, bukan dibuat — metadata diperlakukan sebagai satu-satunya daftar
 * pengaturan yang sah.
 */
class SettingService
{
    public const DISK = 'public';

    private const DIRECTORY = 'settings';

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Seluruh pengaturan, dikelompokkan dan diurutkan sesuai Setting::GROUPS.
     *
     * @return Collection<string, Collection<int, Setting>>
     */
    public function grouped(): Collection
    {
        return Setting::orderBy('order_no')->orderBy('id')->get()
            ->groupBy('group_name')
            ->sortBy(fn ($rows, string $group) => $this->groupRank($group));
    }

    /**
     * Simpan nilai baru dan kembalikan jumlah pengaturan yang benar-benar berubah.
     *
     * @param  array<string, mixed>  $values  nilai non-berkas, dikunci key_name
     * @param  array<string, UploadedFile|null>  $files
     * @param  array<string, mixed>  $removals  kunci berkas yang diminta dikosongkan
     */
    public function save(array $values, array $files = [], array $removals = []): int
    {
        $before = [];
        $after = [];

        foreach (Setting::all() as $setting) {
            $key = $setting->key_name;

            $new = $setting->isFile()
                ? $this->fileValue($setting, $files[$key] ?? null, filter_var($removals[$key] ?? false, FILTER_VALIDATE_BOOLEAN))
                // Kunci yang tidak ikut terkirim dibiarkan apa adanya. Tanpa ini,
                // request sebagian akan mengosongkan pengaturan yang tak disentuh.
                : (array_key_exists($key, $values) ? $this->scalarValue($setting, $values[$key]) : $setting->value);

            if ($new === $setting->value) {
                continue;
            }

            $before[$key] = $setting->value;
            $after[$key] = $new;

            $setting->update(['value' => $new]);
        }

        if ($after !== []) {
            $this->logger->record('update', 'settings', 'app', $before, $after, 'settings');
            setting_flush();
        }

        return count($after);
    }

    /** URL publik sebuah berkas pengaturan, atau null bila belum diunggah. */
    public function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    // ------------------------------------------------------------------

    private function scalarValue(Setting $setting, mixed $input): ?string
    {
        if ($setting->value_type === 'boolean') {
            return filter_var($input, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        // String kosong disimpan sebagai NULL supaya helper setting() jatuh ke
        // nilai bawaan yang dipasang pemanggil, bukan ke teks kosong.
        if ($input === null || $input === '') {
            return null;
        }

        return match ($setting->value_type) {
            'integer' => (string) (int) $input,
            'json' => json_encode(json_decode((string) $input, true), JSON_UNESCAPED_UNICODE),
            default => (string) $input,
        };
    }

    private function fileValue(Setting $setting, ?UploadedFile $file, bool $remove): ?string
    {
        if ($file) {
            $this->deleteFile($setting->value);

            // Nama berkas dibuat ulang, tidak memakai nama dari klien — nama
            // asli bisa mengandung path traversal atau ekstensi ganda.
            $name = $setting->key_name.'-'.Str::random(8).'.'
                .strtolower($file->getClientOriginalExtension());

            return $file->storeAs(self::DIRECTORY, $name, self::DISK);
        }

        if ($remove) {
            $this->deleteFile($setting->value);

            return null;
        }

        return $setting->value;
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Kelompok tak dikenal ditaruh di belakang, tanpa membuat halaman gagal. */
    private function groupRank(string $group): int
    {
        $rank = array_search($group, array_keys(Setting::GROUPS), true);

        return $rank === false ? PHP_INT_MAX : $rank;
    }
}
