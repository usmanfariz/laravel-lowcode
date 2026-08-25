@extends('layouts.adminlte.app')
@section('title', 'Role & Izin')
@section('page-title', 'Role & Izin')

@section('content')
<div class="card">
    <div class="card-header">
        @can('role.create')
            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Role
            </a>
        @endcan
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama</th><th>Cakupan Data</th>
                    <th class="text-center">Izin</th><th class="text-center">Pengguna</th>
                    <th class="text-center">Status</th><th style="width:110px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td><code>{{ $role->code }}</code>
                        @if ($role->is_system)<span class="badge badge-info ml-1">sistem</span>@endif
                    </td>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->data_scope }}</td>
                    <td class="text-center">{{ $role->permissions_count }}</td>
                    <td class="text-center">{{ $role->users_count }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $role->is_active ? 'success' : 'secondary' }}">
                            {{ $role->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        @can('role.edit')
                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                        @endcan
                        @can('role.delete')
                            @unless ($role->is_system)
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline"
                                      onsubmit="return confirm('Hapus role ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada role.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
