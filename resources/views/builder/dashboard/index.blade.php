@extends('layouts.adminlte.app')
@section('title', 'Atur Dashboard')
@section('page-title', 'Atur Dashboard')

@php $editing = $widgets->firstWhere('id', (int) request('edit')); @endphp

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Widget</h3>
                <div class="card-tools">
                    <a href="{{ url('dashboard') }}" class="btn btn-xs btn-default" target="_blank">
                        <i class="fas fa-external-link-alt mr-1"></i>Lihat Dashboard
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th style="width:30px"></th><th>#</th><th>Judul</th><th>Jenis</th>
                            <th>Sumber</th><th class="text-center">Lebar</th>
                            <th class="text-center">Aktif</th><th style="width:80px"></th></tr>
                    </thead>
                    <tbody id="sortable">
                    @forelse ($widgets as $widget)
                        <tr data-id="{{ $widget->id }}" class="{{ $editing?->id === $widget->id ? 'table-warning' : '' }}">
                            <td class="text-center text-muted handle" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                            <td class="text-muted small order-no">{{ $widget->order_no }}</td>
                            <td>
                                @if ($widget->icon)<i class="{{ $widget->icon }} mr-1 text-muted"></i>@endif
                                {{ $widget->title }}
                                <div class="text-muted small"><code>{{ $widget->code }}</code></div>
                            </td>
                            <td><span class="badge badge-{{ $widget->color }}">{{ $widget->type }}</span></td>
                            <td class="small">
                                @switch($widget->type)
                                    @case('stat')
                                        <code>{{ strtoupper($widget->aggregate) }}</code>
                                        {{ $widget->source_table }}{{ $widget->source_column ? '.'.$widget->source_column : '' }}
                                        @break
                                    @case('chart') @case('table')
                                        <i class="fas fa-link text-muted mr-1"></i><code>{{ $widget->report_code }}</code>
                                        @break
                                    @default <span class="text-muted">teks</span>
                                @endswitch
                            </td>
                            <td class="text-center small">{{ $widget->width }}/12</td>
                            <td class="text-center">
                                @if ($widget->is_active)<i class="fas fa-check text-success"></i>
                                @else<i class="fas fa-minus text-muted"></i>@endif
                            </td>
                            <td class="text-center">
                                <a href="?edit={{ $widget->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.dashboard.destroy', $widget) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus widget ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">
                            Belum ada widget. Dashboard akan tampil kosong.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($widgets->isNotEmpty())
                <div class="card-footer text-muted small">
                    <i class="fas fa-arrows-alt-v mr-1"></i>Seret untuk mengubah urutan.
                    Lebar memakai grid 12 kolom, sama seperti tata letak form.
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-5">
        <div class="card card-outline card-{{ $editing ? 'warning' : 'primary' }}">
            <div class="card-header">
                <h3 class="card-title">{{ $editing ? 'Ubah Widget' : 'Tambah Widget' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.dashboard.index') }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.dashboard.update', $editing)
                    : route('builder.dashboard.store') }}">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 form-group">
                            <label>Kode <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $editing->code ?? '') }}" required placeholder="total_produk">
                            @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-7 form-group">
                            <label>Judul <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $editing->title ?? '') }}" required>
                            @error('title')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Jenis Widget <span class="text-danger">*</span></label>
                        <select name="type" id="w-type" class="form-control">
                            @foreach ([
                                'stat' => 'Angka ringkas — hitungan atau jumlah',
                                'chart' => 'Grafik — menumpang report',
                                'table' => 'Tabel ringkas — menumpang report',
                                'text' => 'Catatan teks',
                            ] as $v => $l)
                                <option value="{{ $v }}" @selected(old('type', $editing->type ?? 'stat') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- stat --}}
                    <div id="w-stat">
                        <div class="form-group">
                            <label>Tabel Sumber <span class="text-danger">*</span></label>
                            <select name="source_table" class="form-control js-select2 @error('source_table') is-invalid @enderror">
                                <option value="">— pilih —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}"
                                        @selected(old('source_table', $editing->source_table ?? '') === $source->table_name)>
                                        {{ $source->table_name }} — {{ $source->label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_table')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="row">
                            <div class="col-5 form-group">
                                <label>Agregat</label>
                                <select name="aggregate" id="w-agg" class="form-control">
                                    @foreach (['count' => 'COUNT', 'sum' => 'SUM', 'avg' => 'AVG',
                                               'min' => 'MIN', 'max' => 'MAX'] as $v => $l)
                                        <option value="{{ $v }}" @selected(old('aggregate', $editing->aggregate ?? 'count') === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-7 form-group">
                                <label>Kolom</label>
                                <input type="text" name="source_column" class="form-control @error('source_column') is-invalid @enderror"
                                       value="{{ old('source_column', $editing->source_column ?? '') }}"
                                       placeholder="hanya untuk selain COUNT">
                                @error('source_column')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Penyaring</label>
                            <textarea name="filter_text" rows="2" class="form-control text-monospace" style="font-size:12px"
                                      placeholder="status=published">{{ old('filter_text', $editing && $editing->filter ? collect($editing->filter)->map(fn($v,$k) => $k.'='.$v)->implode("\n") : '') }}</textarea>
                            <small class="form-text text-muted">Satu kondisi per baris, format <code>kolom=nilai</code>.</small>
                        </div>
                        <div class="form-group">
                            <label>Format Angka</label>
                            <select name="format" class="form-control">
                                @foreach (['number' => 'Angka', 'decimal' => 'Desimal',
                                           'currency' => 'Mata uang', 'percentage' => 'Persen'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('format', $editing->format ?? 'number') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- chart / table --}}
                    <div id="w-report">
                        <div class="form-group">
                            <label>Report <span class="text-danger">*</span></label>
                            <select name="report_code" class="form-control js-select2 @error('report_code') is-invalid @enderror">
                                <option value="">— pilih —</option>
                                @foreach ($reports as $report)
                                    <option value="{{ $report->code }}"
                                        @selected(old('report_code', $editing->report_code ?? '') === $report->code)>
                                        {{ $report->name }} ({{ $report->code }}) — {{ $report->type }}
                                    </option>
                                @endforeach
                            </select>
                            @error('report_code')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">
                                Izin report-nya tetap berlaku: yang tak berhak membukanya
                                juga tak melihat widget ini.
                            </small>
                        </div>
                        <div class="form-group" id="w-limit">
                            <label>Baris Ditampilkan</label>
                            <input type="number" name="row_limit" class="form-control" min="1" max="50"
                                   value="{{ old('row_limit', $editing->row_limit ?? 5) }}">
                        </div>
                    </div>

                    {{-- text --}}
                    <div class="form-group" id="w-text">
                        <label>Isi <span class="text-danger">*</span></label>
                        <textarea name="content" rows="4" class="form-control @error('content') is-invalid @enderror">{{ old('content', $editing->content ?? '') }}</textarea>
                        @error('content')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-4 form-group">
                            <label>Lebar</label>
                            <input type="number" name="width" class="form-control" min="1" max="12"
                                   value="{{ old('width', $editing->width ?? 3) }}" required>
                        </div>
                        <div class="col-4 form-group">
                            <label>Warna</label>
                            <select name="color" class="form-control">
                                @foreach (['primary','info','success','warning','danger','secondary','dark'] as $c)
                                    <option value="{{ $c }}" @selected(old('color', $editing->color ?? 'info') === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4 form-group">
                            <label>Urutan</label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $widgets->count() + 1) }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Ikon</label>
                            <input type="text" name="icon" class="form-control"
                                   value="{{ old('icon', $editing->icon ?? '') }}" placeholder="fas fa-box">
                        </div>
                        <div class="col-6 form-group">
                            <label>Tautan</label>
                            <input type="text" name="link_url" class="form-control"
                                   value="{{ old('link_url', $editing->link_url ?? '') }}" placeholder="/forms/product">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Izin</label>
                        <select name="permission_code" class="form-control js-select2">
                            <option value="">— terlihat semua yang login —</option>
                            @foreach ($permissions as $code)
                                <option value="{{ $code }}" @selected(old('permission_code', $editing->permission_code ?? '') === $code)>
                                    {{ $code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active"
                               name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
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
    $('.js-select2').select2({ width: '100%' });

    function sync() {
        const t = $('#w-type').val();
        $('#w-stat').toggle(t === 'stat');
        $('#w-report').toggle(t === 'chart' || t === 'table');
        $('#w-limit').toggle(t === 'table');
        $('#w-text').toggle(t === 'text');
    }
    $('#w-type').on('change', sync);
    sync();

    const el = document.getElementById('sortable');
    if (el && el.querySelector('tr[data-id]')) {
        Sortable.create(el, {
            handle: '.handle', animation: 150,
            onEnd: function () {
                const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();
                $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });
                $.post('{{ route('builder.dashboard.reorder') }}', { order: order })
                    .fail(() => alert('Gagal menyimpan urutan.'));
            },
        });
    }
});
</script>
@endpush
