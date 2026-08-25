@extends('layouts.adminlte.app')
@section('title', 'Kolom — '.$report->name)
@section('page-title', 'Kolom: '.$report->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.reports.index') }}">Report Builder</a></li>
    <li class="breadcrumb-item active">{{ $report->code }}</li>
@endsection

@php $editing = $columns->firstWhere('id', (int) request('edit')); @endphp

@section('content')
@include('builder.reports._nav')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Kolom</h3>
                <span class="text-muted small ml-2"><i class="fas fa-arrows-alt-v mr-1"></i>Seret untuk mengurutkan.</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th style="width:30px"></th><th>#</th><th>Label</th><th>Sumber</th>
                            <th>Agregat</th><th>Format</th><th class="text-center">Grup</th>
                            <th class="text-center">Total</th><th style="width:80px"></th></tr>
                    </thead>
                    <tbody id="sortable">
                    @forelse ($columns as $column)
                        <tr data-id="{{ $column->id }}" class="{{ $editing?->id === $column->id ? 'table-warning' : '' }}">
                            <td class="text-center text-muted handle" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                            <td class="text-muted small order-no">{{ $column->order_no }}</td>
                            <td>{{ $column->label }}</td>
                            <td class="small">
                                @if ($column->source_type === 'expression')
                                    <i class="fas fa-superscript text-warning mr-1"></i><code>{{ $column->expression }}</code>
                                @else
                                    <code>{{ $column->column_name }}</code>
                                @endif
                            </td>
                            <td>
                                @if ($column->hasAggregate())
                                    <span class="badge badge-info">{{ $column->aggregate }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td><span class="badge badge-light border">{{ $column->format }}</span></td>
                            <td class="text-center">@if ($column->is_group_column)<i class="fas fa-check text-success"></i>@endif</td>
                            <td class="text-center">@if ($column->show_total)<i class="fas fa-check text-success"></i>@endif</td>
                            <td class="text-center">
                                <a href="?edit={{ $column->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.reports.columns.destroy', [$report, $column]) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus kolom ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">
                            Belum ada kolom. Report tanpa kolom tidak menampilkan apa pun.
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
                <h3 class="card-title">{{ $editing ? 'Ubah Kolom' : 'Tambah Kolom' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.reports.columns.index', $report) }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.reports.columns.update', [$report, $editing])
                    : route('builder.reports.columns.store', $report) }}">
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
                        <label>Jenis Sumber</label>
                        <select name="source_type" id="source_type" class="form-control">
                            <option value="column" @selected(old('source_type', $editing->source_type ?? 'column') === 'column')>Kolom</option>
                            <option value="expression" @selected(old('source_type', $editing->source_type ?? '') === 'expression')
                                @unless ($canExpression) disabled @endunless>
                                Ekspresi SQL{{ $canExpression ? '' : ' (butuh system.raw_query)' }}
                            </option>
                        </select>
                    </div>

                    <div class="form-group" id="grp-column">
                        <label>Kolom <span class="text-danger">*</span></label>
                        <select name="column_name" class="form-control js-select2 @error('column_name') is-invalid @enderror">
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

                    <div class="form-group" id="grp-expression">
                        <label>Ekspresi <span class="text-danger">*</span></label>
                        <input type="text" name="expression" class="form-control @error('expression') is-invalid @enderror"
                               value="{{ old('expression', $editing->expression ?? '') }}" placeholder="p.price * p.stock">
                        @error('expression')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Hanya referensi kolom, aritmetika, dan fungsi yang diizinkan.
                            Subquery dan kata kunci SQL ditolak.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Agregat</label>
                            <select name="aggregate" class="form-control @error('aggregate') is-invalid @enderror">
                                @foreach ([
                                    'none' => '— tanpa agregat —', 'sum' => 'SUM', 'avg' => 'AVG',
                                    'count' => 'COUNT', 'count_distinct' => 'COUNT DISTINCT',
                                    'min' => 'MIN', 'max' => 'MAX',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('aggregate', $editing->aggregate ?? 'none') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Format</label>
                            <select name="format" class="form-control">
                                @foreach ([
                                    'text' => 'Teks', 'number' => 'Angka', 'decimal' => 'Desimal',
                                    'currency' => 'Mata uang', 'percentage' => 'Persen',
                                    'date' => 'Tanggal', 'datetime' => 'Tanggal & jam',
                                    'boolean' => 'Ya/Tidak', 'badge' => 'Badge',
                                ] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('format', $editing->format ?? 'text') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 form-group">
                            <label>Desimal</label>
                            <input type="number" name="decimal_places" class="form-control" min="0" max="6"
                                   value="{{ old('decimal_places', $editing->decimal_places ?? 2) }}">
                        </div>
                        <div class="col-4 form-group">
                            <label>Rata</label>
                            <select name="align" class="form-control">
                                @foreach (['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('align', $editing->align ?? 'left') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4 form-group">
                            <label>Urutan</label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $columns->count() + 1) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Lebar</label>
                        <input type="text" name="width" class="form-control"
                               value="{{ old('width', $editing->width ?? '') }}" placeholder="140px">
                    </div>

                    @foreach ([
                        'is_visible' => 'Tampilkan',
                        'is_sortable' => 'Bisa diurutkan',
                        'is_searchable' => 'Ikut pencarian',
                        'is_group_column' => 'Kolom pengelompokan (GROUP BY)',
                        'show_total' => 'Tampilkan total',
                        'is_active' => 'Aktif',
                    ] as $name => $label)
                        <div class="custom-control custom-switch mb-1">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1"
                                   @checked(old($name, $editing->$name ?? in_array($name, ['is_visible', 'is_sortable', 'is_active'], true)))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @error('is_group_column')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                    @error('is_searchable')<span class="text-danger small d-block">{{ $message }}</span>@enderror
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

    const sync = () => {
        const isExpr = $('#source_type').val() === 'expression';
        $('#grp-column').toggle(!isExpr);
        $('#grp-expression').toggle(isExpr);
    };
    $('#source_type').on('change', sync);
    sync();

    const el = document.getElementById('sortable');
    if (el && el.querySelector('tr[data-id]')) {
        Sortable.create(el, {
            handle: '.handle', animation: 150,
            onEnd: function () {
                const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();
                $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });
                $.post('{{ route('builder.reports.reorder', [$report, 'columns']) }}', { order: order })
                    .fail(() => alert('Gagal menyimpan urutan.'));
            },
        });
    }
});
</script>
@endpush
