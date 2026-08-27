<?php

namespace App\Contracts;

use App\Models\Form;
use App\Models\User;

/**
 * Kode Anda yang dijalankan oleh sebuah tombol aksi form.
 *
 * Handler didaftarkan di `config/lowcode.php`, lalu dipilih dari builder lewat
 * kuncinya. Metadata tidak pernah menyebut nama class: kalau boleh, siapa pun
 * yang dapat menyunting metadata bisa menjalankan class apa pun di aplikasi ini.
 */
interface FormActionHandler
{
    /**
     * Jalankan aksinya.
     *
     * Dijalankan di dalam transaksi database, jadi melempar exception akan
     * membatalkan seluruh perubahan yang sudah dilakukan handler ini.
     *
     * @param  array<int, string>  $ids  primary key baris terpilih; bisa kosong
     *                                   untuk aksi toolbar yang tidak butuh baris
     * @return string pesan yang ditampilkan ke pengguna setelah berhasil
     *
     * @throws \App\Exceptions\ActionFailedException bila aksi harus dibatalkan
     *                                               dengan pesan yang terbaca
     */
    public function handle(Form $form, array $ids, User $user): string;
}
