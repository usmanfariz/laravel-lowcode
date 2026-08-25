@extends('layouts.adminlte.app')
@section('title', 'Log Aktivitas')
@section('page-title', 'Log Aktivitas')

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
        </div>
    </div>
    <div class="card-body">
        <form id="form-filter" onsubmit="return false;">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Pengguna</label>
                    <select name="user_id" class="form-control js-select2">
                        <option value="">— semua —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>Aksi</label>
                    <select name="event" class="form-control">
                        <option value="">— semua —</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}">{{ $event }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Tabel</label>
                    <select name="table_name" class="form-control js-select2">
                        <option value="">— semua —</option>
                        @foreach ($tables as $table)
                            <option value="{{ $table }}">{{ $table }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="col-md-2 form-group">
                    <label>Sampai</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="btn-apply">
                <i class="fas fa-search mr-1"></i> Tampilkan
            </button>
            <button type="button" class="btn btn-default btn-sm" id="btn-reset">Reset</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Riwayat</h3>
        <div class="card-tools">
            <form method="POST" action="{{ route('activity-logs.prune') }}" class="form-inline"
                  onsubmit="return confirm('Hapus permanen log yang lebih tua dari jumlah hari tersebut?')">
                @csrf
                <label class="small text-muted mr-2 mb-0">Buang log lebih tua dari</label>
                <input type="number" name="days" value="90" min="7" max="3650"
                       class="form-control form-control-sm mr-2" style="width:80px">
                <span class="small text-muted mr-2">hari</span>
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-broom mr-1"></i>Buang</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <table id="tbl-logs" class="table table-bordered table-striped table-sm w-100">
            <thead>
                <tr>
                    <th style="width:150px">Waktu</th>
                    <th>Pengguna</th><th style="width:90px">Aksi</th>
                    <th>Sasaran</th><th>Modul</th>
                    <th style="width:110px">IP</th>
                    <th style="width:80px" class="text-center">Berubah</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    const BADGE = { create: 'success', update: 'info', delete: 'danger' };

    const table = $('#tbl-logs').DataTable({
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        ajax: {
            url: '{{ route('activity-logs.data') }}',
            data: d => { $('#form-filter').serializeArray().forEach(f => d[f.name] = f.value); },
        },
        columns: [
            { data: 'created_at' },
            { data: 'user', orderable: false },
            {
                data: 'event', orderable: false, className: 'text-center',
                render: v => '<span class="badge badge-' + (BADGE[v] || 'secondary') + '">' + v + '</span>',
            },
            { data: 'target', orderable: false, defaultContent: '<span class="text-muted">—</span>' },
            { data: 'module', orderable: false, defaultContent: '<span class="text-muted">—</span>' },
            { data: 'ip_address', orderable: false, defaultContent: '' },
            {
                data: 'changed', orderable: false, className: 'text-center',
                render: v => v ? '<span class="badge badge-light border">' + v + ' kolom</span>'
                              : '<span class="text-muted">—</span>',
            },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: id => $('#row-actions').html().replace(/__ID__/g, id),
            },
        ],
        language: {
            processing: 'Memuat…', search: 'Cari:', lengthMenu: 'Tampil _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok',
            paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        },
    });

    $('#btn-apply').on('click', () => table.ajax.reload());
    $('#btn-reset').on('click', () => {
        $('#form-filter')[0].reset();
        $('#form-filter select').val(null).trigger('change');
        table.ajax.reload();
    });
});
</script>

<div id="row-actions" class="d-none">
    <a href="{{ url('activity-logs') }}/__ID__" class="btn btn-xs btn-default" title="Rincian">
        <i class="fas fa-eye"></i>
    </a>
</div>
@endpush
