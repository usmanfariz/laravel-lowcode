<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat rumus field tidak bisa dibaca atau menunjuk sesuatu yang tidak
 * ada. Pesannya ditulis untuk dibaca admin di builder.
 */
class FormulaException extends RuntimeException {}
