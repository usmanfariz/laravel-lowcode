/*
 * Warna dan gaya bawaan untuk Chart.js, diambil dari token tema.
 *
 * Paletnya hanya ada satu tempat: variabel --lc-chart-* di theme.css. Berkas ini
 * membacanya saat runtime, jadi mode terang dan gelap otomatis ikut, dan tidak
 * ada lagi daftar hex yang tercecer di beberapa view.
 */
window.LcChart = (function () {
    'use strict';

    const JUMLAH_SLOT = 8;
    let terdaftar = [];

    function token(nama) {
        return getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
    }

    /* Urutan tetap. Slot ke-9 dan seterusnya tidak mendapat hue baru — dijadikan
     * abu netral, karena mengulang warna berarti dua seri tampak identik. */
    function warna(i) {
        return i < JUMLAH_SLOT ? token('--lc-chart-' + (i + 1)) : token('--lc-chart-more');
    }

    function palet(n) {
        const hasil = [];
        for (let i = 0; i < n; i++) hasil.push(warna(i));
        return hasil;
    }

    function grid() { return token('--lc-chart-grid'); }
    function tinta() { return token('--lc-chart-ink'); }

    /* Warna isian area/latar batang: hue yang sama, dilemahkan. */
    function lembut(i) { return warna(i) + '33'; }

    function terapkanBawaan() {
        if (typeof Chart === 'undefined') return;

        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = tinta();

        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.legend.labels.boxHeight = 8;
        Chart.defaults.plugins.legend.labels.padding = 14;

        Chart.defaults.plugins.tooltip.backgroundColor = token('--lc-text');
        Chart.defaults.plugins.tooltip.titleColor = token('--lc-surface');
        Chart.defaults.plugins.tooltip.bodyColor = token('--lc-surface');
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.displayColors = true;
        Chart.defaults.plugins.tooltip.boxPadding = 4;

        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.point.radius = 3;
        Chart.defaults.elements.point.hoverRadius = 5;
        Chart.defaults.elements.bar.borderRadius = 4;
        Chart.defaults.elements.bar.borderSkipped = 'start';
        /* Celah tipis antar irisan agar batas tetap terbaca tanpa garis putih. */
        Chart.defaults.elements.arc.borderWidth = 2;
        Chart.defaults.elements.arc.borderColor = token('--lc-surface');
    }

    /* Sumbu: garis kisi tipis, tanpa garis batas, angka gaya Indonesia. */
    function skala(sumbuNilai) {
        const dasar = {
            grid: { color: grid(), drawBorder: false, lineWidth: 1 },
            border: { display: false },
            ticks: { color: tinta(), padding: 6 },
        };
        const hasil = {};
        hasil[sumbuNilai] = JSON.parse(JSON.stringify(dasar));
        hasil[sumbuNilai].beginAtZero = true;
        hasil[sumbuNilai].ticks.callback = (v) => Number(v).toLocaleString('id-ID');
        hasil[sumbuNilai === 'y' ? 'x' : 'y'] = {
            grid: { display: false },
            border: { display: false },
            ticks: { color: tinta(), padding: 6 },
        };
        return hasil;
    }

    /*
     * Daftarkan chart supaya ikut berubah saat mode terang/gelap diganti.
     * `warnaiUlang` menerima chart-nya dan menetapkan ulang warna datasetnya.
     */
    function daftarkan(chart, warnaiUlang) {
        terdaftar.push({ chart: chart, warnaiUlang: warnaiUlang, kanvas: chart.canvas });
    }

    window.addEventListener('lc-theme-change', function () {
        terapkanBawaan();

        /* Halaman yang membangun ulang chart-nya (mis. report saat filter ganti)
         * meninggalkan instance lama di daftar. Buang yang sudah tidak hidup. */
        terdaftar = terdaftar.filter(function (item) {
            return item.kanvas && Chart.getChart(item.kanvas) === item.chart;
        });

        terdaftar.forEach(function (item) {
            try {
                if (item.warnaiUlang) item.warnaiUlang(item.chart);

                /* Sumbu dan legenda memakai warna yang sudah dibaca ulang. */
                Object.keys(item.chart.options.scales || {}).forEach(function (k) {
                    const s = item.chart.options.scales[k];
                    if (s.ticks) s.ticks.color = tinta();
                    if (s.grid && s.grid.color) s.grid.color = grid();
                });

                item.chart.update('none');
            } catch (e) {
                /* Satu chart bermasalah tidak boleh menghentikan yang lain. */
                console.warn('Gagal mewarnai ulang chart:', e);
            }
        });
    });

    return {
        warna: warna,
        palet: palet,
        lembut: lembut,
        grid: grid,
        tinta: tinta,
        skala: skala,
        terapkanBawaan: terapkanBawaan,
        daftarkan: daftarkan,
    };
})();
