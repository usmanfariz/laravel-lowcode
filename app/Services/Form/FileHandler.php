<?php

namespace App\Services\Form;

use App\Models\FormField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileHandler
{
    private const DISK = 'public';

    /**
     * Simpan berkas unggahan dan kembalikan path relatifnya.
     *
     * Nama berkas dibuat ulang, tidak memakai nama asli dari klien — nama asli
     * bisa mengandung path traversal atau ekstensi ganda.
     */
    public function store(FormField $field, UploadedFile $file): string
    {
        $directory = trim($field->upload_path ?: 'uploads/'.$field->form_id, '/');
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());

        return $file->storeAs($directory, $name, self::DISK);
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
