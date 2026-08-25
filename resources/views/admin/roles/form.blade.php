@extends('layouts.adminlte.app')
@section('title', $role->exists ? 'Ubah Role' : 'Tambah Role')
@section('page-title', $role->exists ? 'Ubah Role' : 'Tambah Role')

@section('content')
<form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Data Role</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Kode <span class="text-danger">*</span></label>
                        @if ($role->exists)
                            <input type="text" class="form-control" value="{{ $role->code }}" disabled>
                            <small class="form-text text-muted">Kode tidak dapat diubah setelah dibuat.</small>
                        @else
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" required placeholder="mis. supervisor">
                            @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        @endif
                    </div>

                    <div class="form-group">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $role->name) }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $role->description) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Cakupan Data <span class="text-danger">*</span></label>
                        <select name="data_scope" class="form-control">
                            @foreach (['all' => 'Semua data', 'own' => 'Data sendiri', 'branch' => 'Data unit/cabang', 'custom' => 'Kustom'] as $v => $label)
                                <option value="{{ $v }}" @selected(old('data_scope', $role->data_scope) === $v)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Dibandingkan dengan <code>users.scope_value</code> lewat kolom scope pada form/report.
                        </small>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $role->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('roles.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Izin</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-xs btn-default" id="check-all">Pilih semua</button>
                        <button type="button" class="btn btn-xs btn-default" id="uncheck-all">Kosongkan</button>
                    </div>
                </div>
                <div class="card-body">
                    @foreach ($groups as $group => $items)
                        <h6 class="text-muted text-uppercase small mt-2">{{ $group }}</h6>
                        <div class="row mb-3">
                            @foreach ($items as $permission)
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm"
                                               id="perm{{ $permission->id }}" name="permissions[]"
                                               value="{{ $permission->id }}"
                                               @checked(in_array($permission->id, old('permissions', $selected)))>
                                        <label class="custom-control-label" for="perm{{ $permission->id }}">
                                            {{ $permission->name }}
                                            <code class="small">{{ $permission->code }}</code>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('#check-all').on('click', () => $('.perm').prop('checked', true));
    $('#uncheck-all').on('click', () => $('.perm').prop('checked', false));
});
</script>
@endpush
