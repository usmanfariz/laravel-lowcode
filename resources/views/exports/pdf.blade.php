<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* DomPDF hanya mendukung sebagian CSS — gaya sengaja dibuat sederhana. */
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 3px 5px; }
        th { background: #eee; text-align: left; font-weight: bold; }
        tr.total td { background: #f4f4f4; font-weight: bold; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Dicetak {{ now()->format('d/m/Y H:i') }}
        @auth oleh {{ auth()->user()->name }} @endauth
        &mdash; {{ count($rows) }} baris
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach

            @if (! empty($totals))
                <tr class="total">
                    @foreach ($headings as $i => $heading)
                        <td>{{ $i === 0 ? 'TOTAL' : ($totals[$i] ?? '') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
