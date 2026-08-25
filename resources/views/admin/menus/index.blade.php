@extends('layouts.adminlte.app')
@section('title', 'Menu')
@section('page-title', 'Menu')

@section('content')
<div class="card">
    <div class="card-header">
        @can('system.menu')
            <a href="{{ route('menus.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Menu
            </a>
        @endcan
        <span class="text-muted small ml-2">
            Perubahan langsung menghapus cache sidebar.
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th>Nama</th><th>Kode</th><th>Jenis</th><th>Tujuan</th>
                    <th>Izin</th><th class="text-center">Urutan</th>
                    <th class="text-center">Status</th><th style="width:110px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @each('admin.menus.row', $tree, 'menu')
                @if ($tree->isEmpty())
                    <tr><td colspan="8" class="text-center text-muted py-3">Belum ada menu.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
