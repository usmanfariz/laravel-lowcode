@extends('layouts.adminlte.app')
@section('title', 'Report Builder')
@section('page-title', 'Report Builder')

@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('builder.reports.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Report Baru
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama</th><th>Tabel Dasar</th><th>Tipe</th>
                    <th class="text-center">Join</th><th class="text-center">Kolom</th>
                    <th class="text-center">Filter</th><th class="text-center">Status</th>
                    <th style="width:230px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td><code>{{ $report->code }}</code></td>
                    <td>{{ $report->name }}</td>
                    <td class="small text-muted">
                        {{ $report->base_table }}
                        @if ($report->base_alias)<span class="text-muted">as {{ $report->base_alias }}</span>@endif
                    </td>
                    <td>
                        <span class="badge badge-light border">{{ $report->type }}</span>
                        @if ($report->source_type === 'raw')
                            <span class="badge badge-warning">raw</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $report->joins_count }}</td>
                    <td class="text-center">{{ $report->columns_count }}</td>
                    <td class="text-center">{{ $report->filters_count }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $report->is_active ? 'success' : 'secondary' }}">
                            {{ $report->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ url('reports/'.$report->code) }}" class="btn btn-xs btn-default" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <a href="{{ route('builder.reports.columns.index', $report) }}" class="btn btn-xs btn-primary">
                            <i class="fas fa-columns mr-1"></i> Kolom
                        </a>
                        <a href="{{ route('builder.reports.filters.index', $report) }}" class="btn btn-xs btn-secondary">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </a>
                        <a href="{{ route('builder.reports.edit', $report) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-cog"></i>
                        </a>
                        <form method="POST" action="{{ route('builder.reports.destroy', $report) }}" class="d-inline"
                              onsubmit="return confirm('Hapus definisi report ini? Tabel sumbernya tidak akan disentuh.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-3">Belum ada report.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
