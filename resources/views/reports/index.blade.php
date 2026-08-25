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
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    const table = $('#tbl-report').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        pageLength: {{ $report->per_page ?: 25 }},
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

    $('#btn-apply').on('click', () => table.ajax.reload());
    $('#btn-reset').on('click', () => {
        $('#form-filter')[0].reset();
        $('#form-filter select').val(null).trigger('change');
        table.ajax.reload();
    });
});
</script>
@endpush
