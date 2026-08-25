@extends('layouts.adminlte.app')
@section('title', 'Pengaturan Report')
@section('page-title', 'Report: '.$report->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.reports.index') }}">Report Builder</a></li>
    <li class="breadcrumb-item active">{{ $report->code }}</li>
@endsection

@section('content')
@include('builder.reports._nav')

<form method="POST" action="{{ route('builder.reports.update', $report) }}">
    @csrf @method('PUT')
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Identitas</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Kode</label>
                            <input type="text" class="form-control" value="{{ $report->code }}" disabled>
                        </div>
                        <div class="col-md-5 form-group">
                            <label>Tabel Dasar</label>
                            <input type="text" class="form-control" value="{{ $report->base_table }}" disabled>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Alias</label>
                            <input type="text" name="base_alias" class="form-control"
                                   value="{{ old('base_alias', $report->base_alias) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $report->name) }}" required>
                            @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Judul Halaman</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $report->title) }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $report->description) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Urut Berdasarkan</label>
                            <select name="default_order_column" class="form-control js-select2">
                                <option value="">— bawaan —</option>
                                @foreach ($references as $group => $items)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($items as $reference)
                                            <option value="{{ $reference }}"
                                                @selected(old('default_order_column', $report->default_order_column) === $reference)>
                                                {{ $reference }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Arah</label>
                            <select name="default_order_direction" class="form-control">
                                <option value="asc" @selected(old('default_order_direction', $report->default_order_direction) === 'asc')>A→Z</option>
                                <option value="desc" @selected(old('default_order_direction', $report->default_order_direction) === 'desc')>Z→A</option>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Baris/Halaman</label>
                            <input type="number" name="per_page" class="form-control" min="5" max="500"
                                   value="{{ old('per_page', $report->per_page) }}" required>
                        </div>
                    </div>

                    <div class="row mb-0">
                        <div class="col-md-6 form-group mb-0">
                            <label>Kolom Scope</label>
                            <select name="scope_column" class="form-control js-select2">
                                <option value="">— tanpa pembatasan per baris —</option>
                                @foreach ($references as $group => $items)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($items as $reference)
                                            <option value="{{ $reference }}"
                                                @selected(old('scope_column', $report->scope_column) === $reference)>
                                                {{ $reference }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Kode Izin</label>
                            <input type="text" name="permission_code" class="form-control"
                                   value="{{ old('permission_code', $report->permission_code) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Mode Sumber</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <select name="source_type" id="source_type" class="form-control @error('source_type') is-invalid @enderror">
                            <option value="builder" @selected(old('source_type', $report->source_type) === 'builder')>
                                Builder — dirakit dari join, kolom, dan filter
                            </option>
                            <option value="raw" @selected(old('source_type', $report->source_type) === 'raw')>
                                Raw SQL — tulis query sendiri
                            </option>
                        </select>
                        @error('source_type')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>

                    <div id="grp-raw">
                        <div class="form-group mb-0">
                            <label>Query</label>
                            <textarea name="raw_query" rows="5" class="form-control text-monospace @error('raw_query') is-invalid @enderror"
                                      style="font-size:12px">{{ old('raw_query', $report->raw_query) }}</textarea>
                            @error('raw_query')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <div class="alert alert-danger mt-2 mb-0 small">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Mode raw melewati seluruh whitelist kolom. Hanya satu pernyataan
                                <code>SELECT</code>; titik koma di tengah, kata kunci DML/DDL,
                                <code>information_schema</code>, dan <code>LOAD_FILE</code> ditolak.
                                Butuh izin <code>system.raw_query</code> dan setelan
                                <code>security.allow_raw_query</code> menyala.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ekspor & Status</h3></div>
                <div class="card-body">
                    @foreach ([
                        'allow_export_excel' => 'Ekspor Excel',
                        'allow_export_csv' => 'Ekspor CSV',
                        'allow_export_pdf' => 'Ekspor PDF',
                        'allow_print' => 'Cetak',
                        'use_soft_delete' => 'Sembunyikan baris terhapus',
                        'is_active' => 'Report aktif',
                    ] as $name => $label)
                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                   name="{{ $name }}" value="1" @checked(old($name, $report->$name))>
                            <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <div class="form-group mt-3 mb-0">
                        <label>Ambang Ekspor Langsung</label>
                        <input type="number" name="export_queue_threshold" class="form-control" min="100" max="50000"
                               value="{{ old('export_queue_threshold', $report->export_queue_threshold) }}">
                        <small class="form-text text-muted">
                            Di atas ini ekspor ditolak dengan pesan agar filter dipersempit.
                            Batas keras sistem 50.000 baris.
                        </small>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-group">
                        <label class="small">Catatan perubahan (disimpan di riwayat versi)</label>
                        <input type="text" name="note" class="form-control form-control-sm"
                               placeholder="mis. tambah filter rentang tanggal">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('builder.reports.index') }}" class="btn btn-default">Kembali</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Riwayat Versi</h3></div>
                <div class="card-body p-0" style="max-height:420px; overflow-y:auto">
                    <table class="table table-sm mb-0">
                        <tbody>
                        @forelse ($versions as $version)
                            <tr>
                                <td style="width:44px"><span class="badge badge-secondary">v{{ $version->version }}</span></td>
                                <td class="small">
                                    {{ $version->note ?: 'tanpa catatan' }}
                                    <div class="text-muted">
                                        {{ \Carbon\Carbon::parse($version->created_at)->format('d/m/Y H:i') }}
                                    </div>
                                </td>
                                <td style="width:44px" class="text-center">
                                    <button type="button" class="btn btn-xs btn-warning js-restore"
                                            data-version="{{ $version->version }}" title="Kembalikan">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted small py-3">
                                Belum ada riwayat. Versi terekam setiap kali definisi diubah.
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Form pemulihan dipisah agar tidak bersarang di dalam form pengaturan. --}}
<form method="POST" id="form-restore" class="d-none"
      action="{{ route('builder.reports.restore', [$report, 0]) }}">
    @csrf
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });
    const sync = () => $('#grp-raw').toggle($('#source_type').val() === 'raw');
    $('#source_type').on('change', sync);
    sync();

    $('.js-restore').on('click', function () {
        const version = $(this).data('version');
        if (!confirm('Kembalikan definisi report ke versi ' + version
            + '? Keadaan sekarang akan disimpan sebagai versi baru.')) return;

        const $form = $('#form-restore');
        $form.attr('action', $form.attr('action').replace(/\/\d+$/, '/' + version)).submit();
    });
});
</script>
@endpush
