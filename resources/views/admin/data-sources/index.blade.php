@extends('layouts.adminlte.app')
@section('title', 'Sumber Data')
@section('page-title', 'Sumber Data')

@section('content')
<div class="alert alert-info">
    <i class="fas fa-shield-alt mr-1"></i>
    <strong>Daftar ini adalah gerbang keamanan engine.</strong>
    Hanya tabel di sini yang boleh disentuh form, report, dan generator.
    Tabel yang tidak terdaftar ditolak, sekalipun namanya sudah terlanjur
    tertulis di metadata.
</div>

<div class="card">
    <div class="card-header">
        <a href="{{ route('data-sources.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Daftarkan Tabel
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th>Tabel</th><th>Label</th><th>Primary Key</th>
                    <th class="text-center">Baca</th><th class="text-center">Tulis</th>
                    <th>Kolom Diblokir</th><th>Dipakai</th>
                    <th class="text-center">Status</th><th style="width:90px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($sources as $source)
                @php $used = $usage[$source->table_name] ?? []; @endphp
                <tr>
                    <td><code>{{ $source->table_name }}</code></td>
                    <td>{{ $source->label }}</td>
                    <td class="small text-muted">{{ $source->primary_key }}</td>
                    <td class="text-center">
                        @if ($source->is_readable)<i class="fas fa-check text-success"></i>
                        @else<i class="fas fa-times text-muted"></i>@endif
                    </td>
                    <td class="text-center">
                        @if ($source->is_writable)
                            <span class="badge badge-warning">boleh</span>
                        @else
                            <span class="badge badge-secondary">baca saja</span>
                        @endif
                    </td>
                    <td class="small">
                        @forelse ($source->blocked_columns ?? [] as $column)
                            <span class="badge badge-danger">{{ $column }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                    <td class="small">
                        @if ($used)
                            <span class="badge badge-info" title="{{ implode(', ', $used) }}">
                                {{ count($used) }} tempat
                            </span>
                        @else
                            <span class="text-muted">belum dipakai</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge badge-{{ $source->is_active ? 'success' : 'secondary' }}">
                            {{ $source->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('data-sources.edit', $source) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" action="{{ route('data-sources.destroy', $source) }}" class="d-inline"
                              onsubmit="return confirm('Cabut tabel ini dari daftar sumber data? Tabelnya sendiri tidak akan disentuh.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-3">
                    Belum ada sumber data. Engine tidak akan bisa membaca tabel apa pun.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($unregistered->isNotEmpty())
<div class="card collapsed-card">
    <div class="card-header">
        <h3 class="card-title">
            Tabel Belum Terdaftar
            <span class="badge badge-secondary">{{ $unregistered->count() }}</span>
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Tabel yang ada di database tapi belum boleh disentuh engine.
            Tabel bawaan Laravel dan tabel metadata engine sengaja tidak ditampilkan.
        </p>
        <div class="d-flex flex-wrap" style="gap:6px">
            @foreach ($unregistered as $table)
                <a href="{{ route('data-sources.create') }}?table={{ $table['name'] }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus mr-1"></i>{{ $table['name'] }}
                    <span class="text-muted small">(~{{ number_format($table['rows'], 0, ',', '.') }} baris)</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
