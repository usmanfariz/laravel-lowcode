@extends('layouts.adminlte.app')
@section('title', $column->exists ? 'Ubah Kolom List' : 'Tambah Kolom List')
@section('page-title', ($column->exists ? 'Ubah Kolom List' : 'Tambah Kolom List').' — '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item"><a href="{{ route('builder.columns.index', $form) }}">Kolom List</a></li>
    <li class="breadcrumb-item active">{{ $column->exists ? 'Ubah' : 'Tambah' }}</li>
@endsection

@section('content')
<form method="POST" action="{{ $column->exists
        ? route('builder.columns.update', [$form, $column])
        : route('builder.columns.store', $form) }}">
    @csrf
    @if ($column->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Sumber Nilai</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Jenis Sumber <span class="text-danger">*</span></label>
                        <select name="source_type" id="source_type" class="form-control">
                            <option value="column" @selected(old('source_type', $column->source_type) === 'column')>
                                Kolom tabel ini
                            </option>
                            <option value="relation" @selected(old('source_type', $column->source_type) === 'relation')>
                                Relasi — ambil nama dari tabel lain
                            </option>
                            <option value="expression" @selected(old('source_type', $column->source_type) === 'expression')
                                @unless ($canExpression) disabled @endunless>
                                Ekspresi SQL{{ $canExpression ? '' : ' (butuh izin system.raw_query)' }}
                            </option>
                        </select>
                    </div>

                    <div id="grp-column">
                        <div class="form-group mb-0">
                            <label>Kolom <span class="text-danger">*</span></label>
                            <select name="column_name" class="form-control js-select2 @error('column_name') is-invalid @enderror">
                                <option value="">— pilih kolom —</option>
                                @foreach ($tableColumns as $tableColumn)
                                    <option value="{{ $tableColumn }}"
                                        @selected(old('column_name', $column->column_name) === $tableColumn)>
                                        {{ $tableColumn }}
                                    </option>
                                @endforeach
                            </select>
                            @error('column_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">
                                Untuk relasi, pilih kolom kunci di <code>{{ $form->table_name }}</code>
                                (biasanya berakhiran <code>_id</code>).
                            </small>
                        </div>
                    </div>

                    <div id="grp-relation">
                        <hr>
                        <div class="form-group">
                            <label>Tabel Relasi <span class="text-danger">*</span></label>
                            <select name="relation_table" class="form-control js-select2 @error('relation_table') is-invalid @enderror">
                                <option value="">— pilih tabel —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}"
                                        @selected(old('relation_table', $column->relation_table) === $source->table_name)>
                                        {{ $source->table_name }} — {{ $source->label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('relation_table')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="row mb-0">
                            <div class="col-6 form-group mb-0">
                                <label>Kolom Kunci <span class="text-danger">*</span></label>
                                <input type="text" name="relation_key" class="form-control @error('relation_key') is-invalid @enderror"
                                       value="{{ old('relation_key', $column->relation_key) }}" placeholder="id">
                                @error('relation_key')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-6 form-group mb-0">
                                <label>Kolom Label <span class="text-danger">*</span></label>
                                <input type="text" name="relation_label" class="form-control @error('relation_label') is-invalid @enderror"
                                       value="{{ old('relation_label', $column->relation_label) }}" placeholder="name">
                                @error('relation_label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div id="grp-expression">
                        <hr>
                        <div class="form-group mb-0">
                            <label>Ekspresi SQL <span class="text-danger">*</span></label>
                            <input type="text" name="expression" class="form-control @error('expression') is-invalid @enderror"
                                   value="{{ old('expression', $column->expression) }}"
                                   placeholder="price * stock">
                            @error('expression')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <div class="alert alert-warning mt-2 mb-0 small">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Ekspresi masuk langsung ke klausa <code>SELECT</code>. Hanya
                                referensi kolom, aritmetika, dan fungsi yang diizinkan
                                (<code>SUM</code>, <code>ROUND</code>, <code>CONCAT</code>,
                                <code>IFNULL</code>, …). Subquery, kata kunci SQL, dan
                                komentar ditolak.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tampilan</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                               value="{{ old('label', $column->label) }}" required>
                        @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <div class="col-7 form-group">
                            <label>Format <span class="text-danger">*</span></label>
                            <select name="format" class="form-control">
                                @foreach ([
                                    'text' => 'Teks', 'number' => 'Angka', 'decimal' => 'Desimal',
                                    'currency' => 'Mata uang', 'percentage' => 'Persen',
                                    'date' => 'Tanggal', 'datetime' => 'Tanggal & jam',
                                    'boolean' => 'Ya / Tidak', 'badge' => 'Badge',
                                    'image' => 'Gambar', 'link' => 'Tautan',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('format', $column->format) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5 form-group">
                            <label>Rata <span class="text-danger">*</span></label>
                            <select name="align" class="form-control">
                                @foreach (['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('align', $column->align) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Lebar Kolom</label>
                            <input type="text" name="width" class="form-control"
                                   value="{{ old('width', $column->width) }}" placeholder="120px">
                        </div>
                        <div class="col-6 form-group">
                            <label>Urutan <span class="text-danger">*</span></label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $column->order_no ?? 0) }}" required>
                        </div>
                    </div>

                    @foreach ([
                        'is_visible' => 'Tampilkan di halaman list',
                        'is_searchable' => 'Ikut dicari lewat kotak pencarian',
                        'is_sortable' => 'Bisa diurutkan',
                    ] as $name => $label)
                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1"
                                   @checked(old($name, $column->$name ?? true))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <div class="alert alert-info small mb-0" id="note-relation">
                        Kolom relasi diambil lewat <code>LEFT JOIN</code>, sehingga pencarian
                        dan pengurutan langsung padanya belum didukung — kedua sakelar itu
                        akan diabaikan.
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('builder.columns.index', $form) }}" class="btn btn-default">Batal</a>
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

    function sync() {
        const type = $('#source_type').val();
        $('#grp-column').toggle(type !== 'expression');
        $('#grp-relation').toggle(type === 'relation');
        $('#grp-expression').toggle(type === 'expression');
        $('#note-relation').toggle(type === 'relation');
    }

    $('#source_type').on('change', sync);
    sync();
});
</script>
@endpush
