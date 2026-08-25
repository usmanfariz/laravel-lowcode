@extends('layouts.adminlte.app')
@section('title', $field->exists ? 'Ubah Field' : 'Tambah Field')
@section('page-title', ($field->exists ? 'Ubah Field: '.$field->field_name : 'Tambah Field').' — '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item"><a href="{{ route('builder.fields.index', [$form, 'detail' => $detail?->id]) }}">{{ $form->code }}</a></li>
    <li class="breadcrumb-item active">{{ $field->exists ? 'Ubah' : 'Tambah' }}</li>
@endsection

@section('content')
<form method="POST" action="{{ $field->exists
        ? route('builder.fields.update', [$form, $field])
        : route('builder.fields.store', $form) }}">
    @csrf
    @if ($field->exists) @method('PUT') @endif
    <input type="hidden" name="form_detail_id" value="{{ $detail?->id }}">

    @if ($detail)
        <div class="alert alert-secondary">
            <i class="fas fa-layer-group mr-1"></i>
            Field ini milik baris detail <strong>{{ $detail->title }}</strong>,
            mengacu ke tabel <code>{{ $detail->table_name }}</code>.
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Dasar</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Kolom Tabel <span class="text-danger">*</span></label>
                        <select name="field_name" class="form-control js-select2 @error('field_name') is-invalid @enderror" required>
                            <option value="">— pilih kolom —</option>
                            @foreach ($columns as $column)
                                <option value="{{ $column }}"
                                    @selected(old('field_name', $field->field_name ?? request('field_name')) === $column)>
                                    {{ $column }}
                                </option>
                            @endforeach
                        </select>
                        @error('field_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Hanya kolom yang benar-benar ada di <code>{{ $tableName }}</code>
                            dan tidak diblokir.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                               value="{{ old('label', $field->label) }}" required>
                        @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Jenis Input <span class="text-danger">*</span></label>
                        <select name="input_type" id="input_type" class="form-control js-select2">
                            @foreach ($inputTypes as $type)
                                <option value="{{ $type }}" @selected(old('input_type', $field->input_type) === $type)>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Lebar (dari 12) <span class="text-danger">*</span></label>
                            <input type="number" name="width" class="form-control" min="1" max="12"
                                   value="{{ old('width', $field->width ?? 6) }}" required>
                        </div>
                        <div class="col-6 form-group">
                            <label>Urutan <span class="text-danger">*</span></label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $field->order_no ?? 0) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Placeholder</label>
                        <input type="text" name="placeholder" class="form-control"
                               value="{{ old('placeholder', $field->placeholder) }}">
                    </div>

                    <div class="form-group">
                        <label>Teks Bantuan</label>
                        <input type="text" name="help_text" class="form-control"
                               value="{{ old('help_text', $field->help_text) }}">
                    </div>

                    <div class="form-group">
                        <label>Nilai Default</label>
                        <input type="text" name="default_value" class="form-control"
                               value="{{ old('default_value', $field->default_value) }}">
                    </div>

                    <div class="form-group mb-0">
                        <label>Aturan Validasi Tambahan</label>
                        <input type="text" name="validation" class="form-control"
                               value="{{ old('validation', $field->validation) }}"
                               placeholder="mis. max:100|min:3">
                        <small class="form-text text-muted">
                            Sintaks Laravel, dipisah <code>|</code>. Aturan dasar dari jenis
                            input dan sumber data sudah ditambahkan otomatis.
                        </small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Sifat</h3></div>
                <div class="card-body">
                    @foreach ([
                        'is_required' => 'Wajib diisi',
                        'is_readonly' => 'Hanya baca',
                        'is_unique' => 'Nilai harus unik',
                        'is_active' => 'Aktif',
                    ] as $name => $label)
                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1"
                                   @checked(old($name, $field->$name ?? ($name === 'is_active')))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Sumber Opsi</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Jenis Sumber</label>
                        <select name="data_source_type" id="data_source_type" class="form-control">
                            @foreach ([
                                'none' => 'Tidak ada',
                                'static' => 'Opsi statis (diketik di sini)',
                                'table' => 'Tabel lain',
                                'enum' => 'Nilai ENUM kolom',
                            ] as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(old('data_source_type', $field->data_source_type ?? 'none') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="grp-table">
                        <div class="form-group">
                            <label>Tabel Sumber</label>
                            <select name="data_source" class="form-control js-select2 @error('data_source') is-invalid @enderror">
                                <option value="">— pilih tabel —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}"
                                        @selected(old('data_source', $field->data_source) === $source->table_name)>
                                        {{ $source->table_name }} — {{ $source->label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('data_source')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">Hanya tabel yang terdaftar di data_sources.</small>
                        </div>

                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Kolom Nilai</label>
                                <input type="text" name="value_field" class="form-control @error('value_field') is-invalid @enderror"
                                       value="{{ old('value_field', $field->value_field) }}" placeholder="id">
                                @error('value_field')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-6 form-group">
                                <label>Kolom Label</label>
                                <input type="text" name="label_field" class="form-control @error('label_field') is-invalid @enderror"
                                       value="{{ old('label_field', $field->label_field) }}" placeholder="name">
                                @error('label_field')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Urutkan Berdasarkan</label>
                            <input type="text" name="data_order_by" class="form-control"
                                   value="{{ old('data_order_by', $field->data_order_by) }}">
                        </div>

                        <hr>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Bergantung pada Field</label>
                                <select name="depends_on" class="form-control">
                                    <option value="">— tidak bergantung —</option>
                                    @foreach ($siblings as $sibling)
                                        <option value="{{ $sibling }}"
                                            @selected(old('depends_on', $field->depends_on) === $sibling)>
                                            {{ $sibling }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 form-group">
                                <label>Kolom Penyaring</label>
                                <input type="text" name="depends_column" class="form-control"
                                       value="{{ old('depends_column', $field->depends_column) }}">
                                <small class="form-text text-muted">
                                    Kolom di tabel sumber yang dicocokkan dengan nilai field induk.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div id="grp-static">
                        @error('options')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        <table class="table table-sm" id="tbl-options">
                            <thead>
                                <tr><th>Nilai</th><th>Label</th><th style="width:70px" class="text-center">Default</th><th style="width:40px"></th></tr>
                            </thead>
                            <tbody>
                                @php $rows = old('options', $options->map(fn($o) => (array) $o)->all()); @endphp
                                @forelse ($rows as $i => $row)
                                    <tr>
                                        <td><input type="text" name="options[{{ $i }}][value]" class="form-control form-control-sm" value="{{ $row['value'] ?? '' }}"></td>
                                        <td><input type="text" name="options[{{ $i }}][label]" class="form-control form-control-sm" value="{{ $row['label'] ?? '' }}"></td>
                                        <td class="text-center"><input type="checkbox" name="options[{{ $i }}][is_default]" value="1" @checked(! empty($row['is_default']))></td>
                                        <td class="text-center"><button type="button" class="btn btn-xs btn-danger js-opt-remove"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td><input type="text" name="options[0][value]" class="form-control form-control-sm"></td>
                                        <td><input type="text" name="options[0][label]" class="form-control form-control-sm"></td>
                                        <td class="text-center"><input type="checkbox" name="options[0][is_default]" value="1"></td>
                                        <td class="text-center"><button type="button" class="btn btn-xs btn-danger js-opt-remove"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-default" id="btn-add-opt">
                            <i class="fas fa-plus mr-1"></i> Tambah Opsi
                        </button>
                    </div>

                    <div id="grp-enum" class="alert alert-info mb-0">
                        Nilai diambil dari definisi kolom <code>ENUM</code> di tabel
                        <code>{{ $tableName }}</code>. Isi <strong>Kolom Nilai</strong>
                        dengan nama kolomnya.
                        <div class="form-group mt-2 mb-0">
                            <input type="text" name="value_field_enum" class="form-control"
                                   value="{{ old('value_field', $field->value_field) }}" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="grp-upload">
                <div class="card-header"><h3 class="card-title">Unggahan</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Folder Simpan</label>
                        <input type="text" name="upload_path" class="form-control"
                               value="{{ old('upload_path', $field->upload_path) }}" placeholder="produk">
                    </div>
                    <div class="row mb-0">
                        <div class="col-7 form-group mb-0">
                            <label>Ekstensi Diizinkan</label>
                            <input type="text" name="allowed_extensions" class="form-control"
                                   value="{{ old('allowed_extensions', $field->allowed_extensions) }}"
                                   placeholder="jpg,png,pdf">
                        </div>
                        <div class="col-5 form-group mb-0">
                            <label>Maks. Ukuran (KB)</label>
                            <input type="number" name="max_file_size" class="form-control"
                                   value="{{ old('max_file_size', $field->max_file_size) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('builder.fields.index', [$form, 'detail' => $detail?->id]) }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    const UPLOAD_TYPES = ['file', 'image'];

    // Hanya bagian yang relevan dengan pilihan sekarang yang ditampilkan;
    // form ini punya banyak field yang saling meniadakan.
    function sync() {
        const source = $('#data_source_type').val();
        $('#grp-table').toggle(source === 'table');
        $('#grp-static').toggle(source === 'static');
        $('#grp-enum').toggle(source === 'enum');
        $('#grp-upload').toggle(UPLOAD_TYPES.includes($('#input_type').val()));
    }

    $('#data_source_type, #input_type').on('change', sync);
    sync();

    // Kolom enum memakai value_field yang sama; disalin dari kotak bayangan
    // agar pengguna tidak perlu menebak field mana yang dipakai.
    $('#grp-enum input').on('input', function () {
        $('input[name="value_field"]').val($(this).val());
    }).prop('disabled', false);

    let optIndex = $('#tbl-options tbody tr').length;
    $('#btn-add-opt').on('click', function () {
        $('#tbl-options tbody').append(
            '<tr>' +
            '<td><input type="text" name="options[' + optIndex + '][value]" class="form-control form-control-sm"></td>' +
            '<td><input type="text" name="options[' + optIndex + '][label]" class="form-control form-control-sm"></td>' +
            '<td class="text-center"><input type="checkbox" name="options[' + optIndex + '][is_default]" value="1"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-danger js-opt-remove"><i class="fas fa-times"></i></button></td>' +
            '</tr>');
        optIndex++;
    });

    $(document).on('click', '.js-opt-remove', function () {
        const $body = $(this).closest('tbody');
        if ($body.children('tr').length > 1) $(this).closest('tr').remove();
        else $body.find('input[type=text]').val('');
    });
});
</script>
@endpush
