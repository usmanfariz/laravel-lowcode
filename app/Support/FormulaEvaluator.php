<?php

namespace App\Support;

use App\Exceptions\FormulaException;

/**
 * Rumus field terhitung: penguraian dan penghitungannya.
 *
 * Sengaja berupa parser, bukan penyaring teks. Penyaring harus menebak apa yang
 * berbahaya; parser hanya mengerti angka, nama field, empat operator, kurung,
 * dan sum() — di luar itu tidak bisa dinyatakan sama sekali. Tidak ada eval(),
 * tidak ada pemanggilan fungsi PHP, tidak ada jalan menuju SQL.
 *
 * Tata bahasanya:
 *
 *     expr    := term (('+' | '-') term)*
 *     term    := unary (('*' | '/') unary)*
 *     unary   := '-' unary | primary
 *     primary := ANGKA | 'sum' '(' NAMA '.' NAMA ')' | NAMA | '(' expr ')'
 *
 * Padanannya di sisi klien ada di public/js/lc-formula.js. Keduanya WAJIB
 * memberi hasil sama — layar yang menampilkan angka berbeda dari yang tersimpan
 * lebih buruk daripada tidak menampilkan apa pun.
 */
final class FormulaEvaluator
{
    /** @var array<int, array{jenis: string, nilai: string}> */
    private array $token = [];

    private int $posisi = 0;

    /**
     * Urai rumus menjadi pohon. Melempar bila sintaksnya salah.
     *
     * @return array<mixed>
     */
    public static function parse(string $formula): array
    {
        $parser = new self();
        $parser->token = self::tokenize($formula);
        $parser->posisi = 0;

        if ($parser->token === []) {
            throw new FormulaException('Rumus kosong.');
        }

        $pohon = $parser->expr();

        if ($parser->posisi < count($parser->token)) {
            $sisa = $parser->token[$parser->posisi]['nilai'];
            throw new FormulaException("Ada bagian yang tidak dimengerti di dekat '{$sisa}'.");
        }

        return $pohon;
    }

    /**
     * Nama field yang dipakai rumus, tanpa yang berada di dalam sum().
     *
     * @return array<int, string>
     */
    public static function fieldsUsed(string $formula): array
    {
        return array_values(array_unique(self::kumpulkan(self::parse($formula), 'field')));
    }

    /**
     * Agregat yang dipakai rumus, sebagai pasangan "kodeDetail.namaField".
     *
     * @return array<int, string>
     */
    public static function aggregatesUsed(string $formula): array
    {
        return array_values(array_unique(self::kumpulkan(self::parse($formula), 'sum')));
    }

    /**
     * Hitung nilai rumus.
     *
     * @param  array<string, mixed>  $values  nama field → nilai
     * @param  array<string, mixed>  $sums    "kodeDetail.namaField" → jumlah
     */
    public static function evaluate(string $formula, array $values, array $sums = []): float
    {
        return self::hitung(self::parse($formula), $values, $sums);
    }

    // ------------------------------------------------------------------
    // Pemindaian
    // ------------------------------------------------------------------

    /** @return array<int, array{jenis: string, nilai: string}> */
    private static function tokenize(string $formula): array
    {
        $token = [];
        $panjang = strlen($formula);
        $i = 0;

        while ($i < $panjang) {
            $c = $formula[$i];

            if (ctype_space($c)) {
                $i++;

                continue;
            }

            if (str_contains('+-*/().', $c)) {
                $token[] = ['jenis' => $c, 'nilai' => $c];
                $i++;

                continue;
            }

            if (ctype_digit($c)) {
                $angka = '';
                $titik = false;

                while ($i < $panjang && (ctype_digit($formula[$i]) || ($formula[$i] === '.' && ! $titik))) {
                    // Titik hanya bagian dari angka bila diikuti digit; kalau
                    // tidak, ia pemisah detail seperti pada items.subtotal.
                    if ($formula[$i] === '.') {
                        if (! isset($formula[$i + 1]) || ! ctype_digit($formula[$i + 1])) {
                            break;
                        }
                        $titik = true;
                    }

                    $angka .= $formula[$i];
                    $i++;
                }

                $token[] = ['jenis' => 'angka', 'nilai' => $angka];

                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $nama = '';

                while ($i < $panjang && (ctype_alnum($formula[$i]) || $formula[$i] === '_')) {
                    $nama .= $formula[$i];
                    $i++;
                }

                $token[] = ['jenis' => 'nama', 'nilai' => $nama];

                continue;
            }

            throw new FormulaException("Karakter '{$c}' tidak boleh dipakai dalam rumus.");
        }

        return $token;
    }

    // ------------------------------------------------------------------
    // Penguraian
    // ------------------------------------------------------------------

    /** @return array<mixed> */
    private function expr(): array
    {
        $kiri = $this->term();

        while ($this->lihat('+') || $this->lihat('-')) {
            $op = $this->ambil()['jenis'];
            $kiri = ['bin', $op, $kiri, $this->term()];
        }

        return $kiri;
    }

    /** @return array<mixed> */
    private function term(): array
    {
        $kiri = $this->unary();

        while ($this->lihat('*') || $this->lihat('/')) {
            $op = $this->ambil()['jenis'];
            $kiri = ['bin', $op, $kiri, $this->unary()];
        }

        return $kiri;
    }

    /** @return array<mixed> */
    private function unary(): array
    {
        if ($this->lihat('-')) {
            $this->ambil();

            return ['neg', $this->unary()];
        }

        if ($this->lihat('+')) {
            $this->ambil();

            return $this->unary();
        }

        return $this->primary();
    }

    /** @return array<mixed> */
    private function primary(): array
    {
        $token = $this->ambil();

        if ($token['jenis'] === 'angka') {
            return ['num', (float) $token['nilai']];
        }

        if ($token['jenis'] === '(') {
            $isi = $this->expr();
            $this->wajib(')');

            return $isi;
        }

        if ($token['jenis'] === 'nama') {
            if (strtolower($token['nilai']) === 'sum' && $this->lihat('(')) {
                return $this->sumCall();
            }

            return ['field', $token['nilai']];
        }

        throw new FormulaException("Tidak menyangka menemukan '{$token['nilai']}'.");
    }

    /** @return array<mixed> */
    private function sumCall(): array
    {
        $this->wajib('(');
        $detail = $this->wajib('nama')['nilai'];
        $this->wajib('.');
        $field = $this->wajib('nama')['nilai'];
        $this->wajib(')');

        return ['sum', $detail, $field];
    }

    private function lihat(string $jenis): bool
    {
        return ($this->token[$this->posisi]['jenis'] ?? null) === $jenis;
    }

    /** @return array{jenis: string, nilai: string} */
    private function ambil(): array
    {
        if (! isset($this->token[$this->posisi])) {
            throw new FormulaException('Rumus terpotong sebelum selesai.');
        }

        return $this->token[$this->posisi++];
    }

    /** @return array{jenis: string, nilai: string} */
    private function wajib(string $jenis): array
    {
        if (! $this->lihat($jenis)) {
            $ada = $this->token[$this->posisi]['nilai'] ?? 'akhir rumus';
            throw new FormulaException("Mengharapkan '{$jenis}' tapi menemukan '{$ada}'.");
        }

        return $this->ambil();
    }

    // ------------------------------------------------------------------
    // Penghitungan
    // ------------------------------------------------------------------

    /**
     * @param  array<mixed>  $node
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $sums
     */
    private static function hitung(array $node, array $values, array $sums): float
    {
        return match ($node[0]) {
            'num' => (float) $node[1],
            'field' => (float) ($values[$node[1]] ?? 0),
            'sum' => (float) ($sums[$node[1].'.'.$node[2]] ?? 0),
            'neg' => -self::hitung($node[1], $values, $sums),
            'bin' => self::binary(
                $node[1],
                self::hitung($node[2], $values, $sums),
                self::hitung($node[3], $values, $sums),
            ),
        };
    }

    private static function binary(string $op, float $kiri, float $kanan): float
    {
        return match ($op) {
            '+' => $kiri + $kanan,
            '-' => $kiri - $kanan,
            '*' => $kiri * $kanan,
            // Pembagian nol menghasilkan 0, bukan galat: rumus dihitung ulang
            // tiap ketikan, dan penyebut sesaat bernilai kosong itu wajar.
            '/' => $kanan == 0.0 ? 0.0 : $kiri / $kanan,
        };
    }

    /**
     * @param  array<mixed>  $node
     * @return array<int, string>
     */
    private static function kumpulkan(array $node, string $jenis): array
    {
        return match ($node[0]) {
            'num' => [],
            'field' => $jenis === 'field' ? [$node[1]] : [],
            'sum' => $jenis === 'sum' ? [$node[1].'.'.$node[2]] : [],
            'neg' => self::kumpulkan($node[1], $jenis),
            'bin' => array_merge(
                self::kumpulkan($node[2], $jenis),
                self::kumpulkan($node[3], $jenis),
            ),
        };
    }
}
