@extends('layouts.adminlte.app')
@section('title', $menu->exists ? 'Ubah Menu' : 'Tambah Menu')
@section('page-title', $menu->exists ? 'Ubah Menu' : 'Tambah Menu')

@section('content')
<form method="POST" action="{{ $menu->exists ? route('menus.update', $menu) : route('menus.store') }}">
    @csrf
    @if ($menu->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Menu Induk</label>
                        <select name="parent_id" class="form-control select2">
                            <option value="">— menu tingkat atas —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected(old('parent_id', $menu->parent_id) == $parent->id)>
                                    {{ $parent->name }} ({{ $parent->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<span class="text-danger small">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Kode <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $menu->code) }}" required placeholder="mis. system.users">
                        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $menu->name) }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Jenis Tautan <span class="text-danger">*</span></label>
                        <select name="link_type" id="link_type" class="form-control">
                            @foreach ([
                                'route' => 'Route Laravel',
                                'url' => 'URL langsung',
                                'form' => 'Form dinamis (kode form)',
                                'report' => 'Report dinamis (kode report)',
                                'header' => 'Header (pembungkus saja)',
                            ] as $v => $label)
                                <option value="{{ $v }}" @selected(old('link_type', $menu->link_type) === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" id="grp-target">
                        <label>Tujuan <span class="text-danger">*</span></label>
                        <input type="text" name="target_value" class="form-control @error('target_value') is-invalid @enderror"
                               value="{{ old('target_value', $menu->target_value) }}"
                               placeholder="nama route / URL / kode form / kode report">
                        @error('target_value')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Route yang belum terdaftar tetap tersimpan, tapi tampil sebagai
                            <code>#</code> di sidebar sampai route-nya dibuat.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Izin</label>
                        <select name="permission_code" class="form-control select2">
                            <option value="">— terbuka untuk semua yang login —</option>
                            @foreach ($permissions as $code)
                                <option value="{{ $code }}" @selected(old('permission_code', $menu->permission_code) === $code)>
                                    {{ $code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ikon</label>
                        <input type="text" name="icon" class="form-control"
                               value="{{ old('icon', $menu->icon) }}" placeholder="fas fa-users">
                    </div>

                    <div class="form-group">
                        <label>Urutan <span class="text-danger">*</span></label>
                        <input type="number" name="order_no" class="form-control @error('order_no') is-invalid @enderror"
                               value="{{ old('order_no', $menu->order_no ?? 0) }}" required min="0" max="9999">
                        @error('order_no')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="hidden" name="open_new_tab" value="0">
                        <input type="checkbox" class="custom-control-input" id="open_new_tab" name="open_new_tab" value="1"
                               @checked(old('open_new_tab', $menu->open_new_tab))>
                        <label class="custom-control-label" for="open_new_tab">Buka di tab baru</label>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $menu->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('menus.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.select2').select2({ width: '100%' });

    // Header tidak punya tujuan, jadi field-nya disembunyikan.
    const toggleTarget = () => $('#grp-target').toggle($('#link_type').val() !== 'header');
    $('#link_type').on('change', toggleTarget);
    toggleTarget();
});
</script>
@endpush
