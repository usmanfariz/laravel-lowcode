<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat baris yang hendak disimpan sudah berubah sejak form dibuka.
 */
class StaleRecordException extends RuntimeException {}
