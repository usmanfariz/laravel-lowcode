<nav class="main-header navbar navbar-expand {{ setting('navbar_skin', 'navbar-white navbar-light') }}">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <a class="nav-link" href="#" role="button" id="lc-theme-toggle"
               title="Mode terang / gelap" aria-label="Ganti mode terang atau gelap">
                <i class="fas fa-moon" id="lc-theme-icon"></i>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('exports.index') }}" title="Berkas ekspor">
                <i class="fas fa-file-download"></i>
                <span class="badge badge-warning navbar-badge d-none" id="export-badge"></span>
            </a>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fas fa-user-circle mr-1"></i> {{ auth()->user()->name }}
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <span class="dropdown-item-text text-muted small">
                    {{ auth()->user()->email }}
                </span>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt mr-1"></i> Keluar
                    </button>
                </form>
            </div>
        </li>
    </ul>
</nav>

@push('scripts')
<script>
$(function () {
    // Penanda hanya menampilkan jumlah yang sedang berjalan; tidak menyegar
    // halaman agar tidak mengganggu pekerjaan pengguna.
    function cekEkspor() {
        $.getJSON('{{ route('exports.status') }}', function (d) {
            const n = (d.berjalan || 0);
            $('#export-badge').text(n).toggleClass('d-none', n === 0);
        });
    }
    cekEkspor();
    setInterval(cekEkspor, 30000);
});

// Mode gelap. Nilainya sudah diterapkan lebih awal di <head>; di sini hanya
// menangani penggantian dan menyelaraskan ikon dengan keadaan sekarang.
(function () {
    const akar = document.documentElement;
    const ikon = document.getElementById('lc-theme-icon');

    function selaraskan() {
        ikon.className = akar.getAttribute('data-lc-theme') === 'dark'
            ? 'fas fa-sun'
            : 'fas fa-moon';
    }

    selaraskan();

    document.getElementById('lc-theme-toggle').addEventListener('click', function (e) {
        e.preventDefault();
        const gelap = akar.getAttribute('data-lc-theme') === 'dark';

        if (gelap) {
            akar.removeAttribute('data-lc-theme');
        } else {
            akar.setAttribute('data-lc-theme', 'dark');
        }

        try {
            localStorage.setItem('lc-theme', gelap ? 'light' : 'dark');
        } catch (err) { /* localStorage diblokir: pilihan tidak tersimpan. */ }

        selaraskan();

        // Chart membaca warnanya dari token tema, jadi perlu diberi tahu.
        window.dispatchEvent(new CustomEvent('lc-theme-change'));
    });
})();
</script>
@endpush
