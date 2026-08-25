@extends('layouts.adminlte.app')
@section('title', 'Report Baru')
@section('page-title', 'Report Baru')

@section('content')
<form method="POST" action="{{ route('builder.reports.store') }}">
    @csrf
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Dasar</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Kode <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" required placeholder="penjualan_bulanan">
                            @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">Dipakai di URL: <code>/reports/{kode}</code></small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Tabel Dasar <span class="text-danger">*</span></label>
                            <select name="base_table" class="form-control js-select2 @error('base_table') is-invalid @enderror" required>
                                <option value="">— pilih tabel —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}" @selected(old('base_table') === $source->table_name)>
                                        {{ $source->table_name }} — {{ $source->label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('base_table')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">Tidak dapat diubah setelah report dibuat.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Alias</label>
                            <input type="text" name="base_alias" class="form-control @error('base_alias') is-invalid @enderror"
                                   value="{{ old('base_alias', 'p') }}" placeholder="p">
                            @error('base_alias')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">Dipakai menulis <code>alias.kolom</code>.</small>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Perilaku</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Tipe</label>
                            <select name="type" class="form-control">
                                @foreach (['table' => 'Tabel', 'summary' => 'Ringkasan', 'crosstab' => 'Crosstab', 'chart' => 'Grafik'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('type', 'table') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Baris per Halaman</label>
                            <input type="number" name="per_page" class="form-control" min="5" max="500"
                                   value="{{ old('per_page', 25) }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Arah Urutan</label>
                            <select name="default_order_direction" class="form-control">
                                <option value="asc" @selected(old('default_order_direction') === 'asc')>A→Z</option>
                                <option value="desc" @selected(old('default_order_direction', 'desc') === 'desc')>Z→A</option>
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Kode Izin</label>
                            <input type="text" name="permission_code" class="form-control"
                                   value="{{ old('permission_code') }}" placeholder="report.penjualan.view">
                        </div>
                    </div>

                    <input type="hidden" name="source_type" value="builder">

                    <div class="custom-control custom-switch mb-1">
                        <input type="hidden" name="use_soft_delete" value="0">
                        <input type="checkbox" class="custom-control-input" id="use_soft_delete"
                               name="use_soft_delete" value="1" @checked(old('use_soft_delete', true))>
                        <label class="custom-control-label" for="use_soft_delete">
                            Sembunyikan baris terhapus (<code>deleted_at</code>)
                        </label>
                    </div>
                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active"
                               name="is_active" value="1" @checked(old('is_active', true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>

                    <strong class="small d-block mb-2">Ekspor yang diizinkan</strong>
                    @foreach ([
                        'allow_export_excel' => 'Excel',
                        'allow_export_csv' => 'CSV',
                        'allow_export_pdf' => 'PDF',
                        'allow_print' => 'Cetak',
                    ] as $name => $label)
                        <div class="custom-control custom-switch custom-control-inline">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1" @checked(old($name, true))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Buat Report</button>
                    <a href="{{ route('builder.reports.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>$(function () { $('.js-select2').select2({ width: '100%' }); });</script>
@endpush
