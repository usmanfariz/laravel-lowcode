@extends('layouts.adminlte.app')
@section('title', 'Pengguna')
@section('page-title', 'Pengguna')

@section('content')
<div class="card">
    <div class="card-header">
        @can('user.create')
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Pengguna
            </a>
        @endcan
    </div>
    <div class="card-body">
        <table id="tbl-users" class="table table-bordered table-striped table-sm w-100">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th style="width:110px">Aksi</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#tbl-users').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('users.data') }}',
        order: [[0, 'asc']],
        columns: [
            { data: 'username' },
            { data: 'name' },
            { data: 'email' },
            { data: 'roles', orderable: false, defaultContent: '<span class="text-muted">—</span>' },
            {
                data: 'is_active', orderable: false, className: 'text-center',
                render: v => v
                    ? '<span class="badge badge-success">Aktif</span>'
                    : '<span class="badge badge-secondary">Nonaktif</span>'
            },
            { data: 'last_login_at', orderable: false, defaultContent: '<span class="text-muted">belum pernah</span>' },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function (id) {
                    // Baris aksi dibangun dari template tersembunyi agar URL dan
                    // token CSRF dihasilkan Laravel, bukan dirangkai di JS.
                    return $('#row-actions').html().replace(/__ID__/g, id);
                }
            },
        ],
        language: {
            processing: 'Memuat…', search: 'Cari:', lengthMenu: 'Tampil _MENU_ baris',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok',
            paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
        },
    });

    $('#tbl-users').on('submit', 'form.form-delete', function () {
        return confirm('Hapus pengguna ini?');
    });
});
</script>

<div id="row-actions" class="d-none">
    @can('user.edit')
        <a href="{{ route('users.edit', '__ID__') }}" class="btn btn-xs btn-info" title="Ubah">
            <i class="fas fa-edit"></i>
        </a>
    @endcan
    @can('user.delete')
        <form method="POST" action="{{ route('users.destroy', '__ID__') }}" class="d-inline form-delete">
            @csrf @method('DELETE')
            <button class="btn btn-xs btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
        </form>
    @endcan
</div>
@endpush
