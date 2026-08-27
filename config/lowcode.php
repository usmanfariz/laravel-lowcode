<?php

/*
|--------------------------------------------------------------------------
| Titik ekstensi engine low-code
|--------------------------------------------------------------------------
|
| Berkas ini adalah whitelist kode yang boleh dijalankan engine — sejajar
| dengan `data_sources` untuk tabel dan `SqlExpressionGuard` untuk ekspresi.
|
| Metadata (form_actions, forms) hanya menyimpan KUNCI dari daftar di bawah,
| tidak pernah nama class. Tanpa aturan itu, siapa pun yang bisa menyunting
| metadata lewat builder dapat menjalankan class apa pun di aplikasi ini.
|
*/

return [

    /*
    | Handler aksi: kode yang dijalankan sebuah tombol.
    |
    | Kunci di sini muncul sebagai pilihan di Form Builder → Aksi saat jenis
    | aksinya "handler". Class-nya wajib mengimplementasikan
    | App\Contracts\FormActionHandler.
    |
    | Contoh:
    |   'posting_stok' => App\Lowcode\Handlers\PostingStok::class,
    */
    'handlers' => [
        //
    ],

    /*
    | Hook simpan: kode yang ikut berjalan saat form menulis data.
    |
    | Dikunci per KODE FORM, bukan lewat metadata — logika bisnis yang wajib
    | jalan tidak seharusnya bisa dimatikan dari layar admin. Class-nya wajib
    | mengimplementasikan App\Contracts\FormHook.
    |
    | Contoh:
    |   'nota_penjualan' => [
    |       App\Lowcode\Hooks\NomorNota::class,
    |       App\Lowcode\Hooks\PostingStok::class,
    |   ],
    */
    'hooks' => [
        //
    ],

];
