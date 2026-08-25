<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding: 20px; font-size: 12px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print mb-3">
        <button class="btn btn-primary btn-sm" onclick="window.print()">Cetak</button>
        <button class="btn btn-default btn-sm" onclick="window.close()">Tutup</button>
    </div>

    @include('exports.letterhead')

    <h4 class="mb-1">{{ $title }}</h4>
    <p class="text-muted small">
        Dicetak {{ now()->format(setting('date_format', 'd/m/Y').' H:i') }}
        @auth oleh {{ auth()->user()->name }} @endauth
        &mdash; {{ count($rows) }} baris
    </p>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
            @if (! empty($totals))
                <tr class="font-weight-bold bg-light">
                    @foreach ($headings as $i => $heading)
                        <td>{{ $i === 0 ? 'TOTAL' : ($totals[$i] ?? '') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if ($catatan = setting('print_footer_note'))
        <p class="text-muted small mt-3 mb-0">{{ $catatan }}</p>
    @endif

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
