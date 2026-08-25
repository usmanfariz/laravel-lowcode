{{-- CONTOH — halaman cetak label untuk aksi toolbar form demo. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Produk</title>
    <style>
        body { font-family: sans-serif; padding: 16px; }
        .grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .label {
            border: 1px solid #999; border-radius: 4px; padding: 10px 12px;
            width: 180px; page-break-inside: avoid;
        }
        .code { font-family: monospace; font-size: 12px; color: #666; }
        .name { font-weight: 600; margin: 4px 0; font-size: 13px; }
        .price { font-size: 14px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
        <span style="color:#666; font-size:12px; margin-left:8px">
            {{ $items->count() }} label
        </span>
    </div>

    <div class="grid">
        @forelse ($items as $item)
            <div class="label">
                <div class="code">{{ $item->code }}</div>
                <div class="name">{{ $item->name }}</div>
                <div class="price">Rp {{ number_format((float) $item->price, 0, ',', '.') }}</div>
            </div>
        @empty
            <p>Tidak ada produk untuk dicetak.</p>
        @endforelse
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
