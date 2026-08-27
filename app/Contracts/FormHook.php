<?php

namespace App\Contracts;

use App\Models\Form;
use App\Models\User;

/**
 * Kode Anda yang ikut berjalan saat form menulis data.
 *
 * Semua method dipanggil di dalam transaksi yang sama dengan penulisan
 * barisnya. Melempar exception membatalkan seluruhnya — itu memang gunanya:
 * nota tidak boleh tersimpan kalau stoknya gagal dikurangi.
 *
 * Berbeda dari handler aksi, hook tidak dipasang lewat metadata melainkan
 * didaftarkan per kode form di `config/lowcode.php`. Logika bisnis yang wajib
 * jalan tidak seharusnya bisa dimatikan lewat layar admin.
 *
 * Pakai trait `App\Support\FormHookDefaults` agar cukup menulis method yang
 * benar-benar dibutuhkan.
 */
interface FormHook
{
    /**
     * Dipanggil sebelum baris ditulis. Nilai kembaliannya yang disimpan, jadi
     * di sinilah penomoran otomatis atau nilai turunan diisi.
     *
     * @param  array<string, mixed>  $values  nilai yang akan ditulis
     * @param  array<string, mixed>|null  $before  baris sebelum diubah; null saat pembuatan
     * @return array<string, mixed>
     */
    public function beforeSave(Form $form, array $values, ?array $before, User $user): array;

    /**
     * Dipanggil setelah baris dan seluruh baris detailnya tersimpan.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $before
     */
    public function afterSave(Form $form, mixed $id, array $values, ?array $before, User $user): void;

    /**
     * Dipanggil sebelum baris dihapus. Melempar exception di sini membatalkan
     * penghapusan — tempat menolak "nota yang sudah diposting tidak boleh dihapus".
     *
     * @param  array<string, mixed>  $before
     */
    public function beforeDelete(Form $form, mixed $id, array $before, User $user): void;

    /**
     * @param  array<string, mixed>  $before
     */
    public function afterDelete(Form $form, mixed $id, array $before, User $user): void;
}
