@extends('layouts.adminlte.app')
@section('title', 'Generate CRUD')
@section('page-title', 'Generate CRUD — '.$table)

@section('content')
<form method="POST" action="{{ route('generator.store') }}">
    @csrf
    <input type="hidden" name="table" value="{{ $table }}">

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Pengaturan Form</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Kode Form <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $suggest['code']) }}" required>
                        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">Dipakai di URL: <code>/forms/{kode}</code></small>
                    </div>

                    <div class="form-group">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $suggest['name']) }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Judul Halaman</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}">
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Prefix Izin <span class="text-danger">*</span></label>
                        <input type="text" name="permission_prefix"
                               class="form-control @error('permission_prefix') is-invalid @enderror"
                               value="{{ old('permission_prefix', $suggest['permission_prefix']) }}" required>
                        @error('permission_prefix')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Menghasilkan <code>{{ $suggest['permission_prefix'] }}.view</code>,
                            <code>.create</code>, <code>.edit</code>, <code>.delete</code>,
                            <code>.export</code>, <code>.print</code> — semuanya langsung
                            diberikan ke superadmin.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Kolom Scope</label>
                        <select name="scope_column" class="form-control">
                            <option value="">— tanpa pembatasan per baris —</option>
                            @foreach ($columns as $column)
                                <option value="{{ $column['name'] }}"
                                    @selected(old('scope_column') === $column['name'])>
                                    {{ $column['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Dibandingkan dengan <code>users.scope_value</code> untuk role
                            ber-cakupan <code>branch</code> atau <code>custom</code>.
                        </small>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="create_menu" value="0">
                        <input type="checkbox" class="custom-control-input" id="create_menu"
                               name="create_menu" value="1" @checked(old('create_menu', true))>
                        <label class="custom-control-label" for="create_menu">Buatkan menu sidebar</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic mr-1"></i> Buat Form
                    </button>
                    <a href="{{ route('generator.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Kolom Terdeteksi
                        <span class="badge badge-info">{{ $fields->count() }}</span>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-xs btn-default" id="check-all">Pilih semua</button>
                        <button type="button" class="btn btn-xs btn-default" id="uncheck-all">Kosongkan</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px"></th>
                                <th>Kolom</th><th>Tipe Kolom</th><th>Label</th>
                                <th>Jadi Input</th><th>Sumber Opsi</th>
                                <th class="text-center">Wajib</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($fields as $field)
                            <tr>
                                <td>
                                    <input type="checkbox" name="columns[]" class="col-check"
                                           value="{{ $field['field_name'] }}"
                                           @checked(in_array($field['field_name'], old('columns', $fields->pluck('field_name')->all())))>
                                </td>
                                <td><code>{{ $field['field_name'] }}</code></td>
                                <td class="small text-muted">{{ $field['_column']['type'] }}</td>
                                <td>{{ $field['label'] }}</td>
                                <td><span class="badge badge-light border">{{ $field['input_type'] }}</span></td>
                                <td class="small">
                                    @if ($field['data_source_type'] === 'table')
                                        <i class="fas fa-link text-muted mr-1"></i>
                                        {{ $field['data_source'] }}.{{ $field['label_field'] ?? '?' }}
                                        @unless ($field['label_field'])
                                            <span class="text-warning">(kolom label tak ditemukan)</span>
                                        @endunless
                                    @elseif ($field['data_source_type'] === 'enum')
                                        <span class="text-muted">enum kolom</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($field['is_required'])<i class="fas fa-check text-success"></i>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    Kolom yang diurus engine (<code>created_at</code>, <code>updated_at</code>,
                    <code>deleted_at</code>, <code>created_by</code>, <code>updated_by</code>),
                    primary key auto-increment, dan kolom yang diblokir di
                    <code>data_sources</code> sengaja tidak ditampilkan.
                    Hasil generate masih bisa disunting setelahnya.
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('#check-all').on('click', () => $('.col-check').prop('checked', true));
    $('#uncheck-all').on('click', () => $('.col-check').prop('checked', false));
});
</script>
@endpush
