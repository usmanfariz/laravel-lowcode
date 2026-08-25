@extends('layouts.adminlte.app')
@section('title', 'Form Builder')
@section('page-title', 'Form Builder')

@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('generator.index') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-magic mr-1"></i> Generate dari Tabel
        </a>
        <span class="text-muted small ml-2">
            Form baru dibuat lewat generator; halaman ini untuk menyuntingnya.
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama</th><th>Tabel</th>
                    <th class="text-center">Field</th><th>Prefix Izin</th>
                    <th class="text-center">Status</th><th style="width:200px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($forms as $form)
                <tr>
                    <td><code>{{ $form->code }}</code></td>
                    <td>{{ $form->name }}</td>
                    <td class="small text-muted">{{ $form->table_name }}</td>
                    <td class="text-center">{{ $form->all_fields_count }}</td>
                    <td class="small">{{ $form->permission_prefix ?: '—' }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $form->is_active ? 'success' : 'secondary' }}">
                            {{ $form->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ url('forms/'.$form->code) }}" class="btn btn-xs btn-default" title="Buka">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <a href="{{ route('builder.fields.index', $form) }}" class="btn btn-xs btn-primary">
                            <i class="fas fa-list mr-1"></i> Field
                        </a>
                        <a href="{{ route('builder.columns.index', $form) }}" class="btn btn-xs btn-secondary">
                            <i class="fas fa-columns mr-1"></i> Kolom
                        </a>
                        <a href="{{ route('builder.forms.edit', $form) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-cog mr-1"></i> Pengaturan
                        </a>
                        <form method="POST" action="{{ route('builder.forms.destroy', $form) }}" class="d-inline"
                              onsubmit="return confirm('Hapus definisi form ini? Tabel bisnisnya tidak akan disentuh.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">
                    Belum ada form. Mulai dari <a href="{{ route('generator.index') }}">generator</a>.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
