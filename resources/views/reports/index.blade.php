@extends('layouts.adminlte.app')
@section('title', $report->title ?: $report->name)
@section('page-title', $report->title ?: $report->name)

@section('content')
@if ($filters->isNotEmpty())
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="form-filter" onsubmit="return false;">
            <div class="row">
                @foreach ($filters as $filter)
                    <x-report.filter
                        :filter="$filter"
                        :values="$renderer->valuesFor($filter, [])"
                        :options="$renderer->optionsFor($filter)" />
                @endforeach
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btn-apply">
                <i class="fas fa-search mr-1"></i> Tampilkan
            </button>
            <button type="button" class="btn btn-default btn-sm" id="btn-reset">Reset</button>
        </form>
    </div>
</div>
@endif

@if ($report->type === 'chart')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar mr-1"></i>{{ $report->title ?: $report->name }}
            </h3>
            <div class="card-tools">
                <span class="text-muted small" id="chart-note"></span>
            </div>
        </div>
        <div class="card-body">
            @if ($chartUnavailable)
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ $chartUnavailable }}
                </div>
            @else
                <div style="position:relative; height:340px">
                    <canvas id="chart-report"></canvas>
                </div>
                <div id="chart-error" class="alert alert-danger mt-2 mb-0 d-none"></div>
            @endif
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $report->title ?: $report->name }}</h3>
        <div class="card-tools">
            @if ($report->allow_export_excel)
                <a href="#" class="btn btn-sm btn-success js-export" data-format="xlsx">
                    <i class="fas fa-file-excel mr-1"></i> Excel
                </a>
            @endif
            @if ($report->allow_export_csv)
                <a href="#" class="btn btn-sm btn-default js-export" data-format="csv">
                    <i class="fas fa-file-csv mr-1"></i> CSV
                </a>
            @endif
            @if ($report->allow_export_pdf)
                <a href="#" class="btn btn-sm btn-danger js-export" data-format="pdf">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </a>
            @endif
            @if ($report->allow_print)
                <a href="#" class="btn btn-sm btn-default js-export" data-format="print" target="_blank">
                    <i class="fas fa-print mr-1"></i> Cetak
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($report->description)
            <p class="text-muted small">{{ $report->description }}</p>
        @endif
        <table id="tbl-report" class="table table-bordered table-striped table-sm w-100">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="text-{{ $column->align ?: 'left' }}"
                            @if ($column->width) style="width:{{ $column->width }}" @endif>
                            {{ $column->label }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            @if ($columns->where('show_total', true)->isNotEmpty())
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    @foreach ($columns as $i => $column)
                        <td class="text-{{ $column->align ?: 'left' }}" data-total="c{{ $i }}">
                            {{ $loop->first ? 'Total (halaman ini)' : '' }}
                        </td>
                    @endforeach
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@push('scripts')
@if ($report->type === 'chart' && ! $chartUnavailable)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endif
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    const table = $('#tbl-report').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        pageLength: {{ $report->per_page ?: setting('per_page', 25) }},
        order: [],
        ajax: {
            url: '{{ url("reports/{$report->code}/data") }}',
            // Nilai filter dikirim bersama tiap permintaan, bukan lewat URL,
            // supaya filter tetap berlaku saat berpindah halaman dan sorting.
            data: d => { $('#form-filter').serializeArray().forEach(f => d[f.name] = d[f.name] === undefined
                ? f.value
                : [].concat(d[f.name], f.value)); },
            error: (xhr) => {
                const msg = xhr.responseJSON?.error || 'Gagal memuat data report.';
                $('#tbl-report tbody').html(
                    '<tr><td colspan="{{ $columns->count() }}" class="text-danger text-center py-3">'
                    + $('<div>').text(msg).html() + '</td></tr>');
            },
        },
        columns: [
            @foreach ($columns as $i => $column)
            {
                data: 'c{{ $i }}',
                orderable: {{ $column->is_sortable ? 'true' : 'false' }},
                className: 'text-{{ $column->align ?: 'left' }}',
                defaultContent: '<span class="text-muted">—</span>',
                @if ($column->format === 'boolean')
                render: v => v ? '<span class="badge badge-success">Ya</span>'
                              : '<span class="badge badge-secondary">Tidak</span>',
                @elseif ($column->format === 'badge')
                render: v => v ? '<span class="badge badge-info">' + v + '</span>' : '',
                @endif
            },
            @endforeach
        ],
        drawCallback: function () {
            const totals = this.api().ajax.json()?.totals || {};
            $('[data-total]').each(function () {
                const key = $(this).data('total');
                if (totals[key] !== undefined) $(this).text(totals[key]);
            });
        },
        language: {
            processing: 'Memuat…', search: 'Cari:', lengthMenu: 'Tampil _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok',
            paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        },
    });

    // Ekspor mengikuti filter yang sedang berlaku, jadi URL-nya dirakit dari
    // form filter yang sama — bukan mengekspor seluruh tabel apa adanya.
    $('.js-export').on('click', function (e) {
        e.preventDefault();
        const params = $('#form-filter').serialize();
        const search = $('.dataTables_filter input').val();
        const url = '{{ url("reports/{$report->code}/export") }}/' + $(this).data('format')
            + '?' + params + (search ? '&search=' + encodeURIComponent(search) : '');

        if ($(this).data('format') === 'print') window.open(url, '_blank');
        else window.location = url;
    });

    @if ($report->type === 'chart' && ! $chartUnavailable)
    // --- grafik ---
    const CHART_TYPE = @json($report->chart_type ?: 'bar');
    let chart = null;

    // Sumbu nilai memakai format kolomnya, supaya angka besar tetap terbaca.
    function formatNilai(value, format) {
        if (format === 'currency') return 'Rp ' + value.toLocaleString('id-ID');
        if (format === 'percentage') return value.toLocaleString('id-ID') + '%';
        return value.toLocaleString('id-ID');
    }

    function chartConfig(payload) {
        const isPie = CHART_TYPE === 'pie' || CHART_TYPE === 'doughnut';
        const isArea = CHART_TYPE === 'area';

        const datasets = payload.datasets.map(d => {
            const base = { label: d.label, data: d.data };

            if (isPie) {
                // Lingkaran mewarnai per irisan, bukan per deret.
                return Object.assign(base, {
                    backgroundColor: payload.labels.map((_, i) =>
                        payload.datasets.length > 1 ? d.color : PIE_COLORS[i % PIE_COLORS.length]),
                });
            }

            return Object.assign(base, {
                backgroundColor: isArea ? d.color + '33' : d.color,
                borderColor: d.color,
                borderWidth: 2,
                fill: isArea,
                tension: (CHART_TYPE === 'line' || isArea) ? 0.3 : 0,
            });
        });

        return {
            type: isPie ? CHART_TYPE : (CHART_TYPE === 'area' ? 'line' : (CHART_TYPE === 'horizontal_bar' ? 'bar' : CHART_TYPE)),
            data: { labels: payload.labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: CHART_TYPE === 'horizontal_bar' ? 'y' : 'x',
                plugins: {
                    legend: { display: payload.datasets.length > 1 || isPie },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const d = payload.datasets[ctx.datasetIndex];
                                return d.label + ': ' + formatNilai(ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed, d.format);
                            },
                        },
                    },
                },
                scales: isPie ? {} : {
                    [CHART_TYPE === 'horizontal_bar' ? 'x' : 'y']: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('id-ID') },
                    },
                },
            },
        };
    }

    const PIE_COLORS = ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1',
                        '#20c997','#d63384','#0dcaf0','#ffc107','#6610f2'];

    function muatGrafik() {
        const params = $('#form-filter').serialize();
        const search = $('.dataTables_filter input').val();

        $.getJSON('{{ url("reports/{$report->code}/chart") }}?' + params
            + (search ? '&search=' + encodeURIComponent(search) : ''))
            .done(function (payload) {
                $('#chart-error').addClass('d-none');
                $('#chart-note').text(payload.truncated
                    ? payload.total + ' teratas ditampilkan — selengkapnya di tabel di bawah'
                    : payload.total + ' baris');

                if (chart) chart.destroy();
                chart = new Chart(document.getElementById('chart-report'), chartConfig(payload));
            })
            .fail(function (xhr) {
                $('#chart-error').removeClass('d-none')
                    .text(xhr.responseJSON?.error || 'Gagal memuat grafik.');
            });
    }

    muatGrafik();
    @endif

    $('#btn-apply').on('click', () => {
        table.ajax.reload();
        @if ($report->type === 'chart' && ! $chartUnavailable) muatGrafik(); @endif
    });
    $('#btn-reset').on('click', () => {
        $('#form-filter')[0].reset();
        $('#form-filter select').val(null).trigger('change');
        table.ajax.reload();
        @if ($report->type === 'chart' && ! $chartUnavailable) muatGrafik(); @endif
    });
});
</script>
@endpush
