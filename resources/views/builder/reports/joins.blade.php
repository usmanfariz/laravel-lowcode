@extends('layouts.adminlte.app')
@section('title', 'Join — '.$report->name)
@section('page-title', 'Join: '.$report->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.reports.index') }}">Report Builder</a></li>
    <li class="breadcrumb-item active">{{ $report->code }}</li>
@endsection

@php $editing = $joins->firstWhere('id', (int) request('edit')); @endphp

@section('content')
@include('builder.reports._nav')

<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Daftar Join</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Jenis</th><th>Tabel</th><th>Kondisi</th>
                            <th class="text-center">Aktif</th><th style="width:80px"></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($joins as $join)
                        <tr class="{{ $editing?->id === $join->id ? 'table-warning' : '' }}">
                            <td class="text-muted small">{{ $join->order_no }}</td>
                            <td><span class="badge badge-light border">{{ $join->join_type }}</span></td>
                            <td class="small"><code>{{ $join->table_name }}</code> as <code>{{ $join->alias() }}</code></td>
                            <td class="small">
                                <code>{{ $join->first_column }}</code>
                                {{ $join->operator }}
                                <code>{{ $join->second_column }}</code>
                            </td>
                            <td class="text-center">
                                @if ($join->is_active)<i class="fas fa-check text-success"></i>
                                @else<i class="fas fa-minus text-muted"></i>@endif
                            </td>
                            <td class="text-center">
                                <a href="?edit={{ $join->id }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('builder.reports.joins.destroy', [$report, $join]) }}"
                                      class="d-inline" onsubmit="return confirm('Hapus join ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">
                            Belum ada join. Report hanya membaca tabel dasar.
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
                <h3 class="card-title">{{ $editing ? 'Ubah Join' : 'Tambah Join' }}</h3>
                @if ($editing)
                    <div class="card-tools">
                        <a href="{{ route('builder.reports.joins.index', $report) }}" class="btn btn-xs btn-default">Batal</a>
                    </div>
                @endif
            </div>
            <form method="POST" action="{{ $editing
                    ? route('builder.reports.joins.update', [$report, $editing])
                    : route('builder.reports.joins.store', $report) }}">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-5 form-group">
                            <label>Jenis <span class="text-danger">*</span></label>
                            <select name="join_type" class="form-control">
                                @foreach (['left' => 'LEFT', 'inner' => 'INNER', 'right' => 'RIGHT'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('join_type', $editing->join_type ?? 'left') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-7 form-group">
                            <label>Tabel <span class="text-danger">*</span></label>
                            <select name="table_name" class="form-control js-select2 @error('table_name') is-invalid @enderror" required>
                                <option value="">— pilih —</option>
                                @foreach ($tables as $source)
                                    <option value="{{ $source->table_name }}"
                                        @selected(old('table_name', $editing->table_name ?? '') === $source->table_name)>
                                        {{ $source->table_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('table_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alias <span class="text-danger">*</span></label>
                        <input type="text" name="table_alias" class="form-control @error('table_alias') is-invalid @enderror"
                               value="{{ old('table_alias', $editing->table_alias ?? '') }}" required placeholder="k">
                        @error('table_alias')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label>Kondisi <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="first_column" class="form-control"
                                   value="{{ old('first_column', $editing->first_column ?? '') }}"
                                   placeholder="k.id" required>
                            <div class="input-group-prepend input-group-append" style="width:70px">
                                <select name="operator" class="form-control">
                                    @foreach (['=', '!=', '>', '>=', '<', '<='] as $op)
                                        <option value="{{ $op }}" @selected(old('operator', $editing->operator ?? '=') === $op)>{{ $op }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="text" name="second_column" class="form-control"
                                   value="{{ old('second_column', $editing->second_column ?? '') }}"
                                   placeholder="p.category_id" required>
                        </div>
                        @error('first_column')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                        @error('second_column')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Tulis <code>alias.kolom</code>. Alias yang sah sekarang:
                            @foreach ($references as $group => $items)
                                <code>{{ explode(' ', $group)[0] }}</code>@if (! $loop->last), @endif
                            @endforeach
                            @if ($editing === null)<br>plus alias baru yang Anda isi di atas.@endif
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-6 form-group mb-0">
                            <label>Urutan</label>
                            <input type="number" name="order_no" class="form-control" min="0"
                                   value="{{ old('order_no', $editing->order_no ?? $joins->count() + 1) }}" required>
                        </div>
                        <div class="col-6 d-flex align-items-end pb-2">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_active"
                                       name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true))>
                                <label class="custom-control-label" for="is_active">Aktif</label>
                            </div>
                        </div>
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
<script>$(function () { $('.js-select2').select2({ width: '100%' }); });</script>
@endpush
