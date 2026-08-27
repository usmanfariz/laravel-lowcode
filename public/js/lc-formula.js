/*
 * Rumus field terhitung — sisi klien.
 *
 * Cerminan persis App\Support\FormulaEvaluator di server, dengan tata bahasa
 * yang sama:
 *
 *     expr    := term (('+' | '-') term)*
 *     term    := unary (('*' | '/') unary)*
 *     unary   := '-' unary | primary
 *     primary := ANGKA | 'sum' '(' NAMA '.' NAMA ')' | NAMA | '(' expr ')'
 *
 * Yang tampil di layar HANYA kenyamanan; yang tersimpan selalu dihitung ulang
 * di server. Kalau keduanya berbeda, yang benar adalah server — tapi keduanya
 * memang harus sama, karena angka di layar yang berbeda dari yang tersimpan
 * lebih membingungkan daripada tidak ada angka sama sekali.
 */
window.LcFormula = (function () {
    'use strict';

    function tokenize(rumus) {
        const token = [];
        let i = 0;

        while (i < rumus.length) {
            const c = rumus[i];

            if (/\s/.test(c)) { i++; continue; }

            if ('+-*/().'.includes(c)) {
                token.push({ jenis: c, nilai: c });
                i++;
                continue;
            }

            if (/[0-9]/.test(c)) {
                let angka = '';
                let titik = false;

                while (i < rumus.length && (/[0-9]/.test(rumus[i]) || (rumus[i] === '.' && !titik))) {
                    if (rumus[i] === '.') {
                        // Titik hanya bagian angka bila diikuti digit; kalau
                        // tidak, ia pemisah seperti pada items.subtotal.
                        if (!/[0-9]/.test(rumus[i + 1] || '')) break;
                        titik = true;
                    }
                    angka += rumus[i];
                    i++;
                }

                token.push({ jenis: 'angka', nilai: angka });
                continue;
            }

            if (/[A-Za-z_]/.test(c)) {
                let nama = '';
                while (i < rumus.length && /[A-Za-z0-9_]/.test(rumus[i])) {
                    nama += rumus[i];
                    i++;
                }
                token.push({ jenis: 'nama', nilai: nama });
                continue;
            }

            throw new Error("Karakter '" + c + "' tidak boleh dipakai dalam rumus.");
        }

        return token;
    }

    function parse(rumus) {
        const token = tokenize(rumus);
        let pos = 0;

        const lihat = (jenis) => (token[pos] || {}).jenis === jenis;

        function ambil() {
            if (pos >= token.length) throw new Error('Rumus terpotong sebelum selesai.');
            return token[pos++];
        }

        function wajib(jenis) {
            if (!lihat(jenis)) {
                const ada = (token[pos] || {}).nilai || 'akhir rumus';
                throw new Error("Mengharapkan '" + jenis + "' tapi menemukan '" + ada + "'.");
            }
            return ambil();
        }

        function expr() {
            let kiri = term();
            while (lihat('+') || lihat('-')) {
                const op = ambil().jenis;
                kiri = ['bin', op, kiri, term()];
            }
            return kiri;
        }

        function term() {
            let kiri = unary();
            while (lihat('*') || lihat('/')) {
                const op = ambil().jenis;
                kiri = ['bin', op, kiri, unary()];
            }
            return kiri;
        }

        function unary() {
            if (lihat('-')) { ambil(); return ['neg', unary()]; }
            if (lihat('+')) { ambil(); return unary(); }
            return primary();
        }

        function primary() {
            const t = ambil();

            if (t.jenis === 'angka') return ['num', parseFloat(t.nilai)];

            if (t.jenis === '(') {
                const isi = expr();
                wajib(')');
                return isi;
            }

            if (t.jenis === 'nama') {
                if (t.nilai.toLowerCase() === 'sum' && lihat('(')) {
                    wajib('(');
                    const detail = wajib('nama').nilai;
                    wajib('.');
                    const field = wajib('nama').nilai;
                    wajib(')');
                    return ['sum', detail, field];
                }
                return ['field', t.nilai];
            }

            throw new Error("Tidak menyangka menemukan '" + t.nilai + "'.");
        }

        if (token.length === 0) throw new Error('Rumus kosong.');

        const pohon = expr();

        if (pos < token.length) {
            throw new Error("Ada bagian yang tidak dimengerti di dekat '" + token[pos].nilai + "'.");
        }

        return pohon;
    }

    function hitung(node, values, sums) {
        switch (node[0]) {
            case 'num': return node[1];
            case 'field': return angka(values[node[1]]);
            case 'sum': return angka(sums[node[1] + '.' + node[2]]);
            case 'neg': return -hitung(node[1], values, sums);
            case 'bin': {
                const kiri = hitung(node[2], values, sums);
                const kanan = hitung(node[3], values, sums);
                if (node[1] === '+') return kiri + kanan;
                if (node[1] === '-') return kiri - kanan;
                if (node[1] === '*') return kiri * kanan;
                // Pembagian nol menghasilkan 0, sama seperti di server.
                return kanan === 0 ? 0 : kiri / kanan;
            }
        }
        return 0;
    }

    function angka(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    /* Pohon di-cache: rumus yang sama diurai sekali, lalu dihitung tiap ketikan. */
    const cache = {};

    function evaluate(rumus, values, sums) {
        if (!(rumus in cache)) {
            cache[rumus] = parse(rumus);
        }
        return hitung(cache[rumus], values || {}, sums || {});
    }

    return { parse: parse, evaluate: evaluate };
})();
