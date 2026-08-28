/*
 * Chatbot bantuan — sisi klien.
 *
 * Panel melayang yang menanyakan cara pemakaian ke endpoint /help/ask. Tidak
 * ada kecerdasan di sini: seluruh pencocokan dikerjakan server, berkas ini
 * hanya menggambar percakapan dan mengingatnya selama sesi peramban.
 *
 * Teks jawaban datang dari layar admin, jadi ia diperlakukan sebagai teks tak
 * dipercaya: di-escape lebih dulu, baru sedikit penanda (**tebal**, `kode`,
 * butir "- ", blok ```) diubah jadi elemen. Tidak ada satu pun HTML dari
 * database yang lolos apa adanya.
 */
window.LcChatbot = (function () {
    'use strict';

    var KUNCI_RIWAYAT = 'lc-bot-riwayat';
    var KUNCI_BUKA = 'lc-bot-buka';
    var MAKS_RIWAYAT = 40;

    var rute = {};
    var akar, panel, log, input, kirim, tombol;
    var saranAwal = [];
    var topik = [];
    var sedangTanya = false;

    // ---------------------------------------------------------------- render

    function escape(teks) {
        return String(teks)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Penanda seadanya, dijalankan SETELAH escape.
     *
     * Sengaja tidak memakai pustaka markdown: yang perlu didukung hanya empat
     * bentuk, dan menarik pustaka penuh berarti menarik juga seluruh bentuk
     * lain yang tidak pernah diuji di sini — termasuk yang bisa menyisipkan
     * tautan dan gambar.
     */
    function format(teks) {
        var html = '';
        var blok = escape(teks).split(/\n{2,}/);

        blok.forEach(function (bagian) {
            var baris = bagian.split('\n');

            if (baris[0].trim().indexOf('```') === 0) {
                var isi = baris.slice(1).filter(function (b) {
                    return b.trim().indexOf('```') !== 0;
                });
                html += '<pre><code>' + isi.join('\n') + '</code></pre>';
                return;
            }

            var butir = baris.filter(function (b) { return /^\s*[-*]\s+/.test(b); });

            if (butir.length === baris.length && butir.length > 0) {
                html += '<ul>' + baris.map(function (b) {
                    return '<li>' + inline(b.replace(/^\s*[-*]\s+/, '')) + '</li>';
                }).join('') + '</ul>';
                return;
            }

            html += '<p>' + baris.map(inline).join('<br>') + '</p>';
        });

        return html;
    }

    function inline(teks) {
        return teks
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    }

    function gambarPesan(pesan) {
        var el = document.createElement('div');
        el.className = 'lc-bot__msg lc-bot__msg--' + pesan.dari;

        var balon = document.createElement('div');
        balon.className = 'lc-bot__bubble';

        if (pesan.dari === 'user') {
            balon.textContent = pesan.teks;
        } else {
            balon.innerHTML =
                (pesan.kategori ? '<span class="lc-bot__tag">' + escape(pesan.kategori) + '</span>' : '') +
                format(pesan.teks);

            if (pesan.link) {
                var a = document.createElement('a');
                a.className = 'lc-bot__link';
                a.href = pesan.link.url;
                a.textContent = pesan.link.label;
                balon.appendChild(a);
            }
        }

        el.appendChild(balon);
        log.appendChild(el);

        if (pesan.saran && pesan.saran.length) {
            gambarSaran(pesan.saranLabel || 'Pertanyaan terkait:', pesan.saran);
        }

        return el;
    }

    function gambarSaran(label, daftar) {
        var kotak = document.createElement('div');
        kotak.className = 'lc-bot__suggest';
        kotak.innerHTML = '<div class="lc-bot__suggest-label">' + escape(label) + '</div>';

        daftar.forEach(function (s) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'lc-bot__chip';
            chip.dataset.artikel = s.id;
            chip.innerHTML = escape(s.question) +
                (s.category ? '<small>' + escape(s.category) + '</small>' : '');
            kotak.appendChild(chip);
        });

        log.appendChild(kotak);
    }

    function keBawah() {
        log.scrollTop = log.scrollHeight;
    }

    // -------------------------------------------------------------- riwayat

    function riwayat() {
        try {
            return JSON.parse(sessionStorage.getItem(KUNCI_RIWAYAT) || '[]');
        } catch (e) {
            return [];
        }
    }

    function simpan(pesan) {
        try {
            var isi = riwayat();
            isi.push(pesan);
            sessionStorage.setItem(KUNCI_RIWAYAT, JSON.stringify(isi.slice(-MAKS_RIWAYAT)));
        } catch (e) { /* penyimpanan diblokir: percakapan tetap jalan, hanya tidak diingat. */ }
    }

    function tulis(pesan, catat) {
        gambarPesan(pesan);
        if (catat !== false) {
            simpan(pesan);
        }
        keBawah();
    }

    // ---------------------------------------------------------------- tanya

    function menunggu() {
        var el = document.createElement('div');
        el.className = 'lc-bot__msg lc-bot__msg--bot';
        el.innerHTML = '<div class="lc-bot__bubble lc-bot__dots"><span></span><span></span><span></span></div>';
        log.appendChild(el);
        keBawah();

        return el;
    }

    function tampilkanJawaban(data) {
        if (data.answered) {
            tulis({
                dari: 'bot',
                kategori: data.answer.category,
                teks: data.answer.answer,
                link: data.answer.link,
                saran: data.related
            });

            return;
        }

        tulis({
            dari: 'bot',
            teks: 'Maaf, saya belum punya jawaban untuk itu. Pertanyaan Anda sudah dicatat '
                + 'supaya bisa dilengkapi admin.',
            saran: data.related,
            saranLabel: data.related && data.related.length
                ? 'Mungkin yang Anda cari:'
                : ''
        });
    }

    function gagal(pesan) {
        tulis({ dari: 'bot', teks: pesan }, false);
    }

    function tanya(pertanyaan) {
        if (sedangTanya || !pertanyaan.trim()) {
            return;
        }

        sedangTanya = true;
        kirim.disabled = true;
        tulis({ dari: 'user', teks: pertanyaan });

        var tunggu = menunggu();

        $.post(rute.ask, { question: pertanyaan })
            .done(function (data) {
                tunggu.remove();
                tampilkanJawaban(data);
            })
            .fail(function (xhr) {
                tunggu.remove();
                gagal(xhr.status === 429
                    ? 'Terlalu banyak pertanyaan dalam waktu singkat. Coba lagi sebentar lagi.'
                    : 'Gagal menghubungi server. Coba lagi.');
            })
            .always(function () {
                sedangTanya = false;
                kirim.disabled = false;
                input.focus();
            });
    }

    function bukaArtikel(id) {
        $.getJSON(rute.article.replace('__id__', id))
            .done(function (data) {
                tulis({ dari: 'user', teks: data.question });
                tampilkanJawaban(data);
            })
            .fail(function () {
                gagal('Artikel itu sudah tidak ada.');
            });
    }

    // ---------------------------------------------------------------- topik

    function tampilkanTopik() {
        if (!topik.length) {
            gagal('Daftar topik belum termuat.');
            return;
        }

        tulis({ dari: 'bot', teks: 'Ini seluruh topik yang saya tahu:' }, false);

        topik.forEach(function (t) {
            gambarSaran(t.category, t.questions);
        });

        keBawah();
    }

    function sapa() {
        tulis({
            dari: 'bot',
            teks: 'Halo! Tanyakan apa saja soal cara memakai aplikasi ini — '
                + 'mendaftarkan tabel, membuat form, mengatur izin, membuat laporan.',
            saran: saranAwal,
            saranLabel: 'Yang sering ditanyakan:'
        }, false);
    }

    function mulaiUlang() {
        try {
            sessionStorage.removeItem(KUNCI_RIWAYAT);
        } catch (e) { /* diabaikan */ }

        log.innerHTML = '';
        sapa();
    }

    // ----------------------------------------------------------------- panel

    function buka() {
        akar.classList.add('is-open');
        panel.hidden = false;

        try {
            sessionStorage.setItem(KUNCI_BUKA, '1');
        } catch (e) { /* diabaikan */ }

        keBawah();
        input.focus();
    }

    function tutup() {
        akar.classList.remove('is-open');
        panel.hidden = true;

        try {
            sessionStorage.removeItem(KUNCI_BUKA);
        } catch (e) { /* diabaikan */ }

        tombol.focus();
    }

    // ------------------------------------------------------------------ init

    function init(opsi) {
        rute = opsi.routes;
        akar = document.getElementById('lc-bot');

        if (!akar) {
            return;
        }

        panel = akar.querySelector('.lc-bot__panel');
        log = akar.querySelector('.lc-bot__log');
        input = akar.querySelector('.lc-bot__input');
        kirim = akar.querySelector('.lc-bot__send');
        tombol = akar.querySelector('.lc-bot__toggle');

        // Percakapan sebelumnya digambar ulang tanpa dicatat lagi, supaya
        // berpindah halaman tidak menghapus jawaban yang sedang dibaca.
        var lama = riwayat();
        lama.forEach(function (p) { gambarPesan(p); });

        tombol.addEventListener('click', buka);

        akar.addEventListener('click', function (e) {
            var aksi = e.target.closest('[data-lc-bot]');

            if (aksi) {
                if (aksi.dataset.lcBot === 'close') { tutup(); }
                if (aksi.dataset.lcBot === 'topics') { tampilkanTopik(); }
                if (aksi.dataset.lcBot === 'reset') { mulaiUlang(); }
                return;
            }

            var chip = e.target.closest('.lc-bot__chip');

            if (chip) {
                bukaArtikel(chip.dataset.artikel);
            }
        });

        akar.querySelector('.lc-bot__form').addEventListener('submit', function (e) {
            e.preventDefault();
            var teks = input.value;
            input.value = '';
            tanya(teks);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) {
                tutup();
            }
        });

        // Saran pembuka dimuat sekali. Gagal memuatnya tidak mematikan
        // chatbot — mengetik pertanyaan tetap bisa.
        $.getJSON(rute.topics)
            .done(function (data) {
                saranAwal = data.featured || [];
                topik = data.topics || [];

                if (!lama.length) {
                    sapa();
                }
            })
            .fail(function () {
                if (!lama.length) {
                    sapa();
                }
            });

        try {
            if (sessionStorage.getItem(KUNCI_BUKA) === '1') {
                buka();
            }
        } catch (e) { /* diabaikan */ }
    }

    return { init: init };
})();
