@extends('layouts.adminlte.app')
@section('title', 'Filter — '.$report->name)
@section('page-title', 'Filter: '.$report->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.reports.index') }}">Report Builder</a></li>
    <li class="breadcrumb-item active">{{ $report->code }}</li>
@endsection

@php
    $editing = $filters->firstWhere('id', (int) request('edit'));

    $optionsText = old('static_options_text', $editing && $editing->static_options
        ? collect($editing->static_options)->map(fn ($l, $v) => $v.'|'.$l)->implode("\n")
        : '');

    $defaultsText = old('default_values_text', $editing && $editing->default_values
        ? implode("\n", $editing->default_values)
        : '');
@endphp

@section('content')
@include('builder.reports._nav')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Filter</h3>
                <span class="text-muted small ml-2"><i class="fas fa-arrows-alt-v mr-1"></i>Seret untuk mengurutkan.</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th style="width:30px"></th><th>#</th><th>Label</th><th>Kolom</th>
                            <th>Operator</th><th>Masukan</th><th>Default</th>
                            <th class="text-center">Wajib</th><th style="width:80px"></th></tr>
                    </thead>
                    <tbody id="sortable">
                    @forelse ($filters as $filter)
                        <tr data-id="{{ $filter->id }}" class="{{ $editing?->id === $filter->id ? 'table-warning' : '' }}">
                            <td class="text-center text-muted handle" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                            <td class="text-muted small order-no">{{ $filter->order_no }}</td>
                            <td>{{ $filter->label }}</td>
                            <td class="small"><code>{{ $filter->column_name }}</code></td>
                            <td><span class="badge badge-light border">{{ $filter->operator }}</span></td>
                            <td class="small text-muted">{{ $filter->input_type }}</td>
                            <td class="small">
                                @if ($filter->default_values)
                                    <code>{{ implode(', ', $filter->default_values) }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">@if ($filter->is_required)<i class="fas fa-check text-success"></i>@endif</td>
                            <td class="text-center">
                                <a href="?edit={{ $filter->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.reports.filters.destroy', [$report, $filter]) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus filter ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">
                            Belum ada filter. Report akan menampilkan seluruh baris.
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
                <h3 class="card-title">{{ $editing ? 'Ubah Filter' : 'Tambah Filter' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.reports.filters.index', $report) }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.reports.filters.update', [$report, $editing])
                    : route('builder.reports.filters.store', $report) }}">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label>Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                               value="{{ old('label', $editing->label ?? '') }}" required>
                        @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Kolom <span class="text-danger">*</span></label>
                        <select name="column_name" class="form-control js-select2 @error('column_name') is-invalid @enderror" required>
                            <option value="">— pilih —</option>
                            @foreach ($references as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $reference)
                                        <option value="{{ $reference }}"
                                            @selected(old('column_name', $editing->column_name ?? '') === $reference)>
                                            {{ $reference }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('column_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Operator <span class="text-danger">*</span></label>
                            <select name="operator" id="operator" class="form-control">
                                @foreach ([
                                    '=' => '= sama dengan', '!=' => '≠ tidak sama',
                                    '>' => '> lebih dari', '>=' => '≥ minimal',
                                    '<' => '< kurang dari', '<=' => '≤ maksimal',
                                    'like' => 'LIKE mengandung', 'not_like' => 'NOT LIKE',
                                    'between' => 'BETWEEN rentang', 'in' => 'IN salah satu',
                                    'not_in' => 'NOT IN', 'is_null' => 'IS NULL kosong',
                                    'is_not_null' => 'IS NOT NULL terisi',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('operator', $editing->operator ?? '=') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Jenis Masukan</label>
                            <select name="input_type" class="form-control">
                                @foreach ([
                                    'text' => 'Teks', 'number' => 'Angka', 'date' => 'Tanggal',
                                    'datetime' => 'Tanggal & jam', 'date_range' => 'Rentang tanggal',
                                    'select' => 'Select', 'select2' => 'Select2',
                                    'multi_select' => 'Multi select', 'checkbox' => 'Checkbox', 'radio' => 'Radio',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('input_type', $editing->input_type ?? 'text') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sumber Opsi</label>
                        <select name="data_source_type" id="data_source_type" class="form-control">
                            @foreach ([
                                'none' => 'Tidak ada', 'static' => 'Opsi statis',
                                'table' => 'Tabel lain',
                            ] as $v => $l)
                                <option value="{{ $v }}" @selected(old('data_source_type', $editing->data_source_type ?? 'none') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="grp-static" class="form-group">
                        <label>Opsi Statis</label>
                        <textarea name="static_options_text" rows="4" class="form-control text-monospace"
                                  style="font-size:12px" placeholder="draft|Draft&#10;published|Published">{{ $optionsText }}</textarea>
                        <small class="form-text text-muted">
                            Satu opsi per baris, format <code>nilai|label</code>.
                            Tanpa <code>|</code>, nilainya dipakai sebagai label.
                        </small>
                    </div>

                    <div id="grp-table">
                        <div class="form-group">
                            <label>Tabel Sumber</label>
                            <select name="data_source" class="form-control js-select2 @error('data_source') is-invalid @enderror">
                                <option value="">— pilih —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}"
                                        @selected(old('data_source', $editing->data_source ?? '') === $source->table_name)>
                                        {{ $source->table_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('data_source')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="row">
                            <div class="col-6 form-group">
                                <label>Kolom Nilai</label>
                                <input type="text" name="value_field" class="form-control @error('value_field') is-invalid @enderror"
                                       value="{{ old('value_field', $editing->value_field ?? '') }}" placeholder="id">
                                @error('value_field')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-6 form-group">
                                <label>Kolom Label</label>
                                <input type="text" name="label_field" class="form-control @error('label_field') is-invalid @enderror"
                                       value="{{ old('label_field', $editing->label_field ?? '') }}" placeholder="name">
                                @error('label_field')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="grp-defaults">
                        <label>Nilai Default</label>
                        <textarea name="default_values_text" rows="2"
                                  class="form-control text-monospace @error('default_values_text') is-invalid @enderror"
                                  style="font-size:12px">{{ $defaultsText }}</textarea>
                        @error('default_values_text')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Satu nilai per baris; disimpan sebagai larik JSON.
                            <span id="hint-arity"></span>
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Lebar (dari 12)</label>
                            <input type="number" name="width" class="form-control" min="2" max="12"
                                   value="{{ old('width', $editing->width ?? 3) }}" required>
                        </div>
                        <div class="col-6 form-group">
                            <label>Urutan</label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $filters->count() + 1) }}" required>
                        </div>
                    </div>

                    @foreach (['is_required' => 'Wajib diisi', 'is_active' => 'Aktif'] as $name => $label)
                        <div class="custom-control custom-switch mb-1">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1"
                                   @checked(old($name, $editing->$name ?? ($name === 'is_active')))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach
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
    $('.js-select2').select2({ width: '100%' });

    // Jumlah nilai default yang masuk akal berbeda per operator; petunjuknya
    // ditampilkan agar tidak perlu ditebak lalu ditolak server.
    const ARITY = {
        between: 'Operator ini butuh tepat dua nilai.',
        in: 'Operator ini menerima banyak nilai.',
        not_in: 'Operator ini menerima banyak nilai.',
        is_null: 'Operator ini tidak menerima nilai.',
        is_not_null: 'Operator ini tidak menerima nilai.',
    };

    function sync() {
        const source = $('#data_source_type').val();
        $('#grp-static').toggle(source === 'static');
        $('#grp-table').toggle(source === 'table');

        const op = $('#operator').val();
        const noValue = op === 'is_null' || op === 'is_not_null';
        $('#grp-defaults').toggle(!noValue);
        $('#hint-arity').text(ARITY[op] || 'Operator ini menerima satu nilai.');
    }

    $('#data_source_type, #operator').on('change', sync);
    sync();

    const el = document.getElementById('sortable');
    if (el && el.querySelector('tr[data-id]')) {
        Sortable.create(el, {
            handle: '.handle', animation: 150,
            onEnd: function () {
                const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();
                $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });
                $.post('{{ route('builder.reports.reorder', [$report, 'filters']) }}', { order: order })
                    .fail(() => alert('Gagal menyimpan urutan.'));
            },
        });
    }
});
</script>
@endpush
