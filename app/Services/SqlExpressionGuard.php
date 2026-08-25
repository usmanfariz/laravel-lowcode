<?php

namespace App\Services;

use RuntimeException;

/**
 * Penyaring ekspresi kolom yang masuk klausa SELECT.
 *
 * Whitelist karakter saja TIDAK CUKUP: "(SELECT password FROM users)" hanya
 * memakai huruf, spasi, dan kurung, sehingga lolos penyaringan karakter dan
 * membocorkan kolom apa pun. Karena itu di sini dipakai tiga lapis:
 *
 *   1. whitelist karakter          — menutup titik koma, komentar, backtick
 *   2. blocklist kata kunci SQL    — menutup subquery dan pernyataan bersarang
 *   3. whitelist nama fungsi       — hanya fungsi yang memang dibutuhkan report
 */
class SqlExpressionGuard
{
    private const ALLOWED_CHARS = "/^[A-Za-z0-9_.,()*\/+\-% ']+$/";

    /** Kata yang tidak pernah sah muncul di ekspresi kolom. */
    private const FORBIDDEN = [
        'select', 'from', 'where', 'join', 'union', 'having', 'group', 'order',
        'insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'grant', 'revoke', 'into', 'outfile', 'dumpfile', 'load_file',
        'information_schema', 'sleep', 'benchmark', 'exec', 'call', 'declare',
        'case', 'when', 'then', 'else', 'end', 'limit', 'offset', 'as',
    ];

    /** Fungsi yang boleh dipakai. Apa pun di luar ini ditolak. */
    private const ALLOWED_FUNCTIONS = [
        'sum', 'avg', 'count', 'min', 'max', 'round', 'abs', 'floor', 'ceil',
        'ceiling', 'truncate_', 'ifnull', 'coalesce', 'nullif', 'concat',
        'concat_ws', 'length', 'lower', 'upper', 'trim', 'date', 'year',
        'month', 'day', 'greatest', 'least',
    ];

    /**
     * @throws RuntimeException bila ekspresi tidak aman
     */
    public function assertSafe(string $expression, string $label = 'kolom'): string
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new RuntimeException("Ekspresi {$label} kosong.");
        }

        if (! preg_match(self::ALLOWED_CHARS, $expression)) {
            throw new RuntimeException("Ekspresi {$label} mengandung karakter yang tidak diizinkan.");
        }

        // Komentar SQL lolos whitelist karakter karena '*' dan '/' memang sah
        // sebagai operator, padahal komentar bisa dipakai menyembunyikan
        // potongan query. Ditolak terpisah.
        foreach (['/*', '*/', '--'] as $comment) {
            if (str_contains($expression, $comment)) {
                throw new RuntimeException("Ekspresi {$label} memuat komentar SQL.");
            }
        }

        if (! $this->balanced($expression)) {
            throw new RuntimeException("Tanda kurung pada ekspresi {$label} tidak seimbang.");
        }

        // Kata kunci dicari di luar literal berkutip agar 'from' sebagai teks
        // biasa tidak ikut tertolak.
        $stripped = preg_replace("/'(?:[^']|'')*'/", "''", $expression);

        foreach (self::FORBIDDEN as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $stripped)) {
                throw new RuntimeException("Ekspresi {$label} memuat kata kunci terlarang '{$word}'.");
            }
        }

        foreach ($this->functionsIn($stripped) as $function) {
            if (! in_array(strtolower($function), self::ALLOWED_FUNCTIONS, true)) {
                throw new RuntimeException("Fungsi '{$function}' tidak diizinkan pada ekspresi {$label}.");
            }
        }

        return $expression;
    }

    /** @return array<int, string> nama fungsi yang dipanggil di ekspresi */
    private function functionsIn(string $expression): array
    {
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $expression, $matches);

        return $matches[1] ?? [];
    }

    private function balanced(string $expression): bool
    {
        $depth = 0;

        foreach (str_split($expression) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}
