@extends('layouts.adminlte.app')
@section('title', $form->title ?: $form->name)
@section('page-title', $form->title ?: $form->name)

@section('content')
<div class="card">
    <div class="card-header">
        @if ($form->allow_create && (! $form->permission('create') || auth()->user()->hasPermission($form->permission('create'))))
            <a href="{{ url("forms/{$form->code}/create") }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah
            </a>
        @endif
        @foreach ($toolbarActions as $action)
            <a href="#" class="btn btn-sm {{ $action['class'] }} js-action"
               data-action="{{ $loop->index }}" data-scope="toolbar">
                @if ($action['icon'])<i class="{{ $action['icon'] }} mr-1"></i>@endif
                {{ $action['label'] }}
            </a>
        @endforeach

        @if ($bulkActions)
            <span class="ml-2 d-none" id="bulk-bar">
                <span class="badge badge-info mr-1"><span id="bulk-count">0</span> terpilih</span>
                @foreach ($bulkActions as $action)
                    <a href="#" class="btn btn-sm {{ $action['class'] }} js-action"
                       data-action="{{ $loop->index }}" data-scope="bulk">
                        @if ($action['icon'])<i class="{{ $action['icon'] }} mr-1"></i>@endif
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </span>
        @endif

        @if ($form->description)
            <span class="text-muted small ml-2">{{ $form->description }}</span>
        @endif

        @php $canExport = ! $form->permission('export') || auth()->user()->hasPermission($form->permission('export')); @endphp
        @if ($canExport && ($form->allow_export || $form->allow_print))
            <div class="card-tools">
                @if ($form->allow_export)
                    <a href="#" class="btn btn-sm btn-success js-export" data-format="xlsx">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="#" class="btn btn-sm btn-default js-export" data-format="csv">
                        <i class="fas fa-file-csv mr-1"></i> CSV
                    </a>
                    <a href="#" class="btn btn-sm btn-danger js-export" data-format="pdf">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                @endif
                @if ($form->allow_print)
                    <a href="#" class="btn btn-sm btn-default js-export" data-format="print">
                        <i class="fas fa-print mr-1"></i> Cetak
                    </a>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body">
        <table id="tbl-form" class="table table-bordered table-striped table-sm w-100">
            <thead>
                <tr>
                    @if ($bulkActions)
                        <th style="width:34px" class="text-center">
                            <input type="checkbox" id="check-all-rows">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th class="text-{{ $column->align ?: 'left' }}"
                            @if ($column->width) style="width:{{ $column->width }}" @endif>
                            {{ $column->label }}
                        </th>
                    @endforeach
                    <th style="width:110px">Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
const ROW_ACTIONS = @json($rowActions);
const TOOLBAR_ACTIONS = @json($toolbarActions);
const BULK_ACTIONS = @json($bulkActions);

/**
 * Kondisi tampil dievaluasi per baris. Nilai dibandingkan sebagai string
 * supaya 1 dan "1" dari MySQL dianggap sama.
 */
function conditionMet(condition, values) {
    if (!condition) return true;
    return Object.keys(condition).every(function (key) {
        const want = condition[key];
        const have = values ? values[key] : undefined;
        if (Array.isArray(want)) return want.map(String).includes(String(have));
        return String(want) === String(have);
    });
}

function renderRowActions(row) {
    return ROW_ACTIONS.filter(a => conditionMet(a.condition, row.__cond)).map(function (a, i) {
        const idx = ROW_ACTIONS.indexOf(a);
        return '<a href="#" class="btn btn-xs ' + a.class + ' ml-1 js-action" '
            + 'data-action="' + idx + '" data-scope="row" data-id="' + row.__id + '" '
            + 'title="' + $('<div>').text(a.label).html() + '">'
            + (a.icon ? '<i class="' + a.icon + '"></i>' : $('<div>').text(a.label).html())
            + '</a>';
    }).join('');
}

$(function () {
    $('#tbl-form').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ url("forms/{$form->code}/data") }}',
        pageLength: {{ $form->per_page ?: 25 }},
        order: [],
        columns: [
            @if ($bulkActions)
            {
                data: '__id', orderable: false, searchable: false, className: 'text-center',
                render: id => '<input type="checkbox" class="row-check" value="' + id + '">',
            },
            @endif
            @foreach ($columns as $i => $column)
            {
                data: 'c{{ $i }}',
                orderable: {{ $column->is_sortable && $column->source_type === 'column' ? 'true' : 'false' }},
                className: 'text-{{ $column->align ?: 'left' }}',
                defaultContent: '<span class="text-muted">—</span>',
                @if ($column->format === 'boolean')
                render: v => v
                    ? '<span class="badge badge-success">Ya</span>'
                    : '<span class="badge badge-secondary">Tidak</span>',
                @elseif ($column->format === 'image')
                render: v => v ? '<img src="/storage/' + v + '" style="max-height:40px">' : '',
                @elseif ($column->format === 'badge')
                render: v => v ? '<span class="badge badge-info">' + v + '</span>' : '',
                @endif
            },
            @endforeach
            {
                data: null, orderable: false, searchable: false, className: 'text-center',
                render: row => $('#row-actions').html().replace(/__ID__/g, row.__id)
                    + renderRowActions(row),
            },
        ],
        language: {
            processing: 'Memuat…', search: 'Cari:', lengthMenu: 'Tampil _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok',
            paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        },
    });

    // Ekspor mengikuti kata pencarian yang sedang aktif di DataTables.
    $('.js-export').on('click', function (e) {
        e.preventDefault();
        const search = $('.dataTables_filter input').val();
        const url = '{{ url("forms/{$form->code}/export") }}/' + $(this).data('format')
            + (search ? '?search=' + encodeURIComponent(search) : '');

        if ($(this).data('format') === 'print') window.open(url, '_blank');
        else window.location = url;
    });

    $('#tbl-form').on('submit', 'form.form-delete', function () {
        return confirm('Hapus data ini?');
    });

    // --- aksi metadata ---
    const table = $('#tbl-form').DataTable();

    function selectedIds() {
        return $('#tbl-form').find('.row-check:checked').map(function () { return this.value; }).get();
    }

    function syncBulkBar() {
        const n = selectedIds().length;
        $('#bulk-count').text(n);
        $('#bulk-bar').toggleClass('d-none', n === 0);
    }

    $('#tbl-form').on('change', '.row-check', syncBulkBar);
    $('#check-all-rows').on('change', function () {
        $('#tbl-form').find('.row-check').prop('checked', this.checked);
        syncBulkBar();
    });
    table.on('draw', function () {
        $('#check-all-rows').prop('checked', false);
        syncBulkBar();
    });

    $(document).on('click', '.js-action', function (e) {
        e.preventDefault();

        const scope = $(this).data('scope');
        const list = scope === 'row' ? ROW_ACTIONS : (scope === 'bulk' ? BULK_ACTIONS : TOOLBAR_ACTIONS);
        const action = list[$(this).data('action')];
        if (!action) return;

        const ids = scope === 'row' ? [String($(this).data('id'))] : selectedIds();

        if (scope === 'bulk' && ids.length === 0) {
            alert('Pilih minimal satu baris terlebih dahulu.');
            return;
        }

        if (action.confirm && !confirm(action.confirm)) return;

        const url = (action.url || '#').replace(/__ID__/g, ids[0] || '');

        if (action.type === 'modal') { $(url).modal('show'); return; }

        if (action.type === 'ajax' || action.method !== 'GET') {
            $.ajax({ url: url, method: action.method, data: { ids: ids } })
                .done(() => table.ajax.reload(null, false))
                .fail(xhr => alert(xhr.responseJSON?.message || 'Aksi gagal dijalankan.'));
            return;
        }

        window.location = url;
    });
});
</script>

<div id="row-actions" class="d-none">
    @if ($form->allow_edit && (! $form->permission('edit') || auth()->user()->hasPermission($form->permission('edit'))))
        <a href="{{ url("forms/{$form->code}") }}/__ID__/edit" class="btn btn-xs btn-info" title="Ubah">
            <i class="fas fa-edit"></i>
        </a>
    @endif
    @if ($form->allow_delete && (! $form->permission('delete') || auth()->user()->hasPermission($form->permission('delete'))))
        <form method="POST" action="{{ url("forms/{$form->code}") }}/__ID__" class="d-inline form-delete">
            @csrf @method('DELETE')
            <button class="btn btn-xs btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
        </form>
    @endif
</div>
@endpush
