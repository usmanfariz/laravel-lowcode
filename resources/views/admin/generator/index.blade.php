@extends('layouts.adminlte.app')
@section('title', 'Generate CRUD')
@section('page-title', 'Generate CRUD dari Tabel')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Pilih Tabel</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Hanya tabel yang terdaftar di <code>data_sources</code> dan boleh dibaca yang
            muncul di sini. Untuk menambah tabel lain, daftarkan dulu sumber datanya.
        </p>

        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Tabel</th><th>Label</th><th class="text-center">Tulis</th>
                    <th>Form yang sudah ada</th><th style="width:130px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tables as $source)
                <tr>
                    <td><code>{{ $source->table_name }}</code></td>
                    <td>{{ $source->label }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $source->is_writable ? 'success' : 'secondary' }}">
                            {{ $source->is_writable ? 'boleh' : 'baca saja' }}
                        </span>
                    </td>
                    <td>
                        @if ($existing->has($source->table_name))
                            <a href="{{ url('forms/'.$existing[$source->table_name]) }}">
                                <code>{{ $existing[$source->table_name] }}</code>
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('generator.preview', $source->table_name) }}"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-magic mr-1"></i> Generate
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">
                    Belum ada sumber data yang terdaftar.
                </td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Tabel <strong>baca saja</strong> tetap bisa di-generate, tapi form-nya
            hanya akan bisa menampilkan data — penyimpanan ditolak sampai
            <code>is_writable</code> dinyalakan.
        </div>
    </div>
</div>
@endsection
