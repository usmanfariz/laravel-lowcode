{{-- CONTOH — halaman cetak label untuk aksi toolbar form demo. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Produk</title>
    <style>
        body { font-family: sans-serif; padding: 16px; margin: 0; }
        .grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .label {
            border: 1px solid #999; border-radius: 4px; padding: 10px;
            width: 200px; page-break-inside: avoid; break-inside: avoid;
            display: flex; flex-direction: column; gap: 4px;
        }
        .code { font-family: monospace; font-size: 11px; color: #666; letter-spacing: .5px; }
        .name { font-weight: 600; font-size: 13px; line-height: 1.25; }
        .price { font-size: 15px; font-weight: 600; }
        .codes { display: flex; align-items: flex-end; gap: 8px; margin-top: 2px; }
        .barcode { flex: 1; min-width: 0; }
        .barcode svg { display: block; width: 100%; height: 38px; }
        .qr svg { display: block; width: 62px; height: 62px; }
        .toolbar { margin-bottom: 12px; font-size: 13px; }
        .toolbar a { margin-right: 6px; text-decoration: none; color: #0a58ca; }
        .toolbar a.active { font-weight: 700; text-decoration: underline; }
        .hint { color: #666; font-size: 12px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .label { border-color: #000; }
        }
    </style>
</head>
<body>
    <div class="no-print toolbar">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>

        <span class="hint" style="margin: 0 10px">Kode:</span>
        @foreach ($modes as $option)
            <a href="{{ request()->fullUrlWithQuery(['kode' => $option]) }}"
               class="{{ $mode === $option ? 'active' : '' }}">{{ $option }}</a>
        @endforeach

        <span class="hint" style="margin-left: 10px">{{ $items->count() }} label</span>
    </div>

    <div class="grid">
        @forelse ($items as $item)
            <div class="label">
                <div class="code">{{ $item->code }}</div>
                <div class="name">{{ $item->name }}</div>
                <div class="price">Rp {{ number_format((float) $item->price, 0, ',', '.') }}</div>

                @if ($item->barcode_svg || $item->qr_svg)
                    <div class="codes">
                        @if ($item->barcode_svg)
                            {{-- SVG dihasilkan server dari data sendiri, bukan masukan bebas. --}}
                            <div class="barcode">{!! $item->barcode_svg !!}</div>
                        @endif
                        @if ($item->qr_svg)
                            <div class="qr">{!! $item->qr_svg !!}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p>Tidak ada produk untuk dicetak.</p>
        @endforelse
    </div>

    @if ($mode !== 'tanpa')
        <p class="no-print hint" style="margin-top:14px">
            Barcode memuat <strong>kode produk</strong> (Code 128, terbaca pemindai umum).
            QR memuat <strong>tautan ke halaman ubah produk</strong>.
        </p>
    @endif

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
