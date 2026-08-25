@extends('layouts.adminlte.app')
@section('title', 'Aksi — '.$form->name)
@section('page-title', 'Tombol Aksi: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item active">{{ $form->code }}</li>
@endsection

@php $editing = $actions->firstWhere('id', (int) request('edit')); @endphp

@section('content')
@include('builder._formnav')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Aksi</h3>
                <span class="text-muted small ml-2"><i class="fas fa-arrows-alt-v mr-1"></i>Seret untuk mengurutkan.</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th style="width:30px"></th><th>#</th><th>Label</th><th>Posisi</th>
                            <th>Jenis</th><th>Tujuan</th><th>Metode</th>
                            <th class="text-center">Aktif</th><th style="width:80px"></th></tr>
                    </thead>
                    <tbody id="sortable">
                    @forelse ($actions as $action)
                        <tr data-id="{{ $action->id }}" class="{{ $editing?->id === $action->id ? 'table-warning' : '' }}">
                            <td class="text-center text-muted handle" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                            <td class="text-muted small order-no">{{ $action->order_no }}</td>
                            <td>
                                @if ($action->icon)<i class="{{ $action->icon }} mr-1 text-muted"></i>@endif
                                {{ $action->label }}
                            </td>
                            <td><span class="badge badge-light border">{{ $action->position }}</span></td>
                            <td class="small text-muted">{{ $action->action_type }}</td>
                            <td class="small"><code>{{ $action->target_value }}</code></td>
                            <td>
                                <span class="badge badge-{{ $action->http_method === 'GET' ? 'secondary' : 'warning' }}">
                                    {{ $action->http_method }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($action->is_active)<i class="fas fa-check text-success"></i>
                                @else<i class="fas fa-minus text-muted"></i>@endif
                            </td>
                            <td class="text-center">
                                <a href="?edit={{ $action->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.actions.destroy', [$form, $action]) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus aksi ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">
                            Belum ada aksi tambahan. Tombol ubah dan hapus bawaan tetap tampil.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card card-outline card-{{ $editing ? 'warning' : 'primary' }}">
            <div class="card-header">
                <h3 class="card-title">{{ $editing ? 'Ubah Aksi' : 'Tambah Aksi' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.actions.index', $form) }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.actions.update', [$form, $editing])
                    : route('builder.actions.store', $form) }}">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 form-group">
                            <label>Kode <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $editing->code ?? '') }}" required placeholder="approve">
                            @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-7 form-group">
                            <label>Label <span class="text-danger">*</span></label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                                   value="{{ old('label', $editing->label ?? '') }}" required>
                            @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Posisi <span class="text-danger">*</span></label>
                            <select name="position" class="form-control @error('position') is-invalid @enderror">
                                @foreach ([
                                    'row' => 'Per baris', 'toolbar' => 'Toolbar atas', 'bulk' => 'Massal (baris terpilih)',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('position', $editing->position ?? 'row') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Jenis Aksi <span class="text-danger">*</span></label>
                            <select name="action_type" class="form-control">
                                @foreach ([
                                    'route' => 'Route Laravel', 'url' => 'URL langsung',
                                    'ajax' => 'Permintaan AJAX', 'modal' => 'Buka modal',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('action_type', $editing->action_type ?? 'route') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tujuan <span class="text-danger">*</span></label>
                        <input type="text" name="target_value" class="form-control @error('target_value') is-invalid @enderror"
                               value="{{ old('target_value', $editing->target_value ?? '') }}" required
                               placeholder="nama route / URL / id modal">
                        @error('target_value')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Metode HTTP <span class="text-danger">*</span></label>
                            <select name="http_method" class="form-control @error('http_method') is-invalid @enderror">
                                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                                    <option value="{{ $m }}" @selected(old('http_method', $editing->http_method ?? 'GET') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                            @error('http_method')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-6 form-group">
                            <label>Ikon</label>
                            <input type="text" name="icon" class="form-control"
                                   value="{{ old('icon', $editing->icon ?? '') }}" placeholder="fas fa-check">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Pesan Konfirmasi</label>
                        <input type="text" name="confirm_message" class="form-control @error('confirm_message') is-invalid @enderror"
                               value="{{ old('confirm_message', $editing->confirm_message ?? '') }}"
                               placeholder="Setujui data ini?">
                        @error('confirm_message')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Wajib untuk aksi selain GET — aksi yang mengubah data mudah
                            terpicu tak sengaja.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-7 form-group">
                            <label>Kode Izin</label>
                            <input type="text" name="permission_code" class="form-control"
                                   value="{{ old('permission_code', $editing->permission_code ?? '') }}">
                        </div>
                        <div class="col-5 form-group">
                            <label>Kelas CSS</label>
                            <input type="text" name="css_class" class="form-control"
                                   value="{{ old('css_class', $editing->css_class ?? '') }}" placeholder="btn-success">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group mb-0">
                            <label>Urutan <span class="text-danger">*</span></label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $actions->count() + 1) }}" required>
                        </div>
                        <div class="col-6 d-flex align-items-end pb-2">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_active"
                                       name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true))>
                                <label class="custom-control-label" for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i> {{ $editing ? 'Simpan' : 'Tambah' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
$(function () {
    const el = document.getElementById('sortable');
    if (!el || !el.querySelector('tr[data-id]')) return;
    Sortable.create(el, {
        handle: '.handle', animation: 150,
        onEnd: function () {
            const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();
            $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });
            $.post('{{ route('builder.forms.reorder', [$form, 'actions']) }}', { order: order })
                .fail(() => alert('Gagal menyimpan urutan.'));
        },
    });
});
</script>
@endpush
