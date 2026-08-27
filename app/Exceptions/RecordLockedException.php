<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat baris memenuhi kondisi penguncian form, sehingga tidak boleh
 * lagi diubah atau dihapus.
 */
class RecordLockedException extends RuntimeException {}
