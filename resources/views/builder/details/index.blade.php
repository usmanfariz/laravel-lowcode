@extends('layouts.adminlte.app')
@section('title', 'Detail — '.$form->name)
@section('page-title', 'Baris Detail: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item active">{{ $form->code }}</li>
@endsection

@php $editing = $details->firstWhere('id', (int) request('edit')); @endphp

@section('content')
@include('builder._formnav')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Daftar Detail</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Kode</th><th>Judul</th><th>Tabel</th>
                            <th>Penghubung</th><th class="text-center">Baris</th>
                            <th class="text-center">Field</th><th style="width:110px"></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($details as $detail)
                        @php $fieldCount = \App\Models\FormField::where('form_detail_id', $detail->id)->count(); @endphp
                        <tr class="{{ $editing?->id === $detail->id ? 'table-warning' : '' }}">
                            <td class="text-muted small">{{ $detail->order_no }}</td>
                            <td><code>{{ $detail->code }}</code></td>
                            <td>{{ $detail->title }}</td>
                            <td class="small"><code>{{ $detail->table_name }}</code></td>
                            <td class="small"><code>{{ $detail->foreign_key }}</code></td>
                            <td class="text-center small">
                                {{ $detail->min_rows ?: 0 }}–{{ $detail->max_rows ?: '∞' }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('builder.fields.index', $form) }}?detail={{ $detail->id }}"
                                   class="badge badge-{{ $fieldCount ? 'info' : 'warning' }}">
                                    {{ $fieldCount }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="?edit={{ $detail->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.details.destroy', [$form, $detail]) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus detail ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">
                            Belum ada detail. Form ini bertipe tunggal.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($details->isNotEmpty())
                <div class="card-footer text-muted small">
                    Field milik baris detail dikelola di halaman <strong>Field</strong>,
                    ditandai dengan <code>form_detail_id</code>. Detail tanpa field tidak
                    akan menggambar apa pun.
                </div>
            @endif
        </div>
    </div>

    <div class="col-md-5">
        <div class="card card-outline card-{{ $editing ? 'warning' : 'primary' }}">
            <div class="card-header">
                <h3 class="card-title">{{ $editing ? 'Ubah Detail' : 'Tambah Detail' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.details.index', $form) }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.details.update', [$form, $editing])
                    : route('builder.details.store', $form) }}">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 form-group">
                            <label>Kode <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $editing->code ?? '') }}" required placeholder="item">
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
                        <label>Tabel Detail <span class="text-danger">*</span></label>
                        <select name="table_name" class="form-control js-select2 @error('table_name') is-invalid @enderror" required>
                            <option value="">— pilih tabel —</option>
                            @foreach ($tables as $source)
                                <option value="{{ $source->table_name }}"
                                    @selected(old('table_name', $editing->table_name ?? '') === $source->table_name)>
                                    {{ $source->table_name }} — {{ $source->label }}
                                </option>
                            @endforeach
                        </select>
                        @error('table_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Hanya tabel yang <strong>boleh ditulis</strong> — engine menyisipkan
                            dan menghapus baris detail setiap kali induknya disimpan.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Primary Key <span class="text-danger">*</span></label>
                            <input type="text" name="primary_key" class="form-control"
                                   value="{{ old('primary_key', $editing->primary_key ?? 'id') }}" required>
                        </div>
                        <div class="col-6 form-group">
                            <label>Kolom Penghubung <span class="text-danger">*</span></label>
                            <input type="text" name="foreign_key" class="form-control"
                                   value="{{ old('foreign_key', $editing->foreign_key ?? '') }}"
                                   required placeholder="invoice_id">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 form-group">
                            <label>Baris Min</label>
                            <input type="number" name="min_rows" class="form-control" min="0"
                                   value="{{ old('min_rows', $editing->min_rows ?? '') }}">
                        </div>
                        <div class="col-4 form-group">
                            <label>Baris Maks</label>
                            <input type="number" name="max_rows" class="form-control @error('max_rows') is-invalid @enderror"
                                   min="1" value="{{ old('max_rows', $editing->max_rows ?? '') }}">
                            @error('max_rows')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-4 form-group">
                            <label>Urutan <span class="text-danger">*</span></label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $details->count() + 1) }}" required>
                        </div>
                    </div>

                    @foreach ([
                        'allow_add' => 'Boleh menambah baris',
                        'allow_delete' => 'Boleh menghapus baris',
                        'show_total_row' => 'Tampilkan baris total',
                        'is_active' => 'Aktif',
                    ] as $name => $label)
                        <div class="custom-control custom-switch mb-1">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1"
                                   @checked(old($name, $editing->$name ?? ($name !== 'show_total_row')))>
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
<script>$(function () { $('.js-select2').select2({ width: '100%' }); });</script>
@endpush
