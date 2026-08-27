<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar handler aksi atau hook untuk membatalkan pekerjaan dengan pesan
 * yang layak dibaca pengguna.
 *
 * Exception lain tetap membatalkan transaksi, tapi pesannya tidak ditampilkan
 * apa adanya — isi pesan exception sembarangan bisa membocorkan detail internal.
 */
class ActionFailedException extends RuntimeException {}
