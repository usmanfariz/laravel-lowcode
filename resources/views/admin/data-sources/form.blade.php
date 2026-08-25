@extends('layouts.adminlte.app')
@section('title', $source->exists ? 'Ubah Sumber Data' : 'Daftarkan Tabel')
@section('page-title', $source->exists ? 'Ubah Sumber Data: '.$source->table_name : 'Daftarkan Tabel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('data-sources.index') }}">Sumber Data</a></li>
    <li class="breadcrumb-item active">{{ $source->exists ? 'Ubah' : 'Daftarkan' }}</li>
@endsection

@php $blocked = old('blocked_columns', $source->blocked_columns ?? []); @endphp

@section('content')
<form method="POST" action="{{ $source->exists
        ? route('data-sources.update', $source)
        : route('data-sources.store') }}">
    @csrf
    @if ($source->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tabel</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Nama Tabel <span class="text-danger">*</span></label>
                        @if ($source->exists)
                            <input type="text" class="form-control" value="{{ $source->table_name }}" disabled>
                            <small class="form-text text-muted">
                                Tidak dapat diubah — metadata menunjuk sumber ini lewat nama tabelnya.
                            </small>
                        @else
                            <select name="table_name" id="table_name"
                                    class="form-control js-select2 @error('table_name') is-invalid @enderror" required>
                                <option value="">— pilih tabel —</option>
                                @foreach ($tables as $table)
                                    <option value="{{ $table['name'] }}"
                                        @selected(old('table_name', $source->table_name) === $table['name'])>
                                        {{ $table['name'] }} (~{{ number_format($table['rows'], 0, ',', '.') }} baris)
                                    </option>
                                @endforeach
                            </select>
                            @error('table_name')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="form-text text-muted">
                                Memilih tabel akan memuat ulang halaman untuk membaca kolomnya.
                            </small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>Label <span class="text-danger">*</span></label>
                        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                               value="{{ old('label', $source->label) }}" required>
                        @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group mb-0">
                        <label>Primary Key <span class="text-danger">*</span></label>
                        @if ($columns)
                            <select name="primary_key" class="form-control @error('primary_key') is-invalid @enderror" required>
                                @foreach ($columns as $column)
                                    <option value="{{ $column }}" @selected(old('primary_key', $source->primary_key) === $column)>
                                        {{ $column }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="primary_key" class="form-control @error('primary_key') is-invalid @enderror"
                                   value="{{ old('primary_key', $source->primary_key ?? 'id') }}" required>
                        @endif
                        @error('primary_key')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Izin</h3></div>
                <div class="card-body">
                    <div class="custom-control custom-switch mb-2">
                        <input type="hidden" name="is_readable" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_readable"
                               name="is_readable" value="1" @checked(old('is_readable', $source->is_readable ?? true))>
                        <label class="custom-control-label" for="is_readable">Boleh dibaca</label>
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="hidden" name="is_writable" value="0">
                        <input type="checkbox" class="custom-control-input @error('is_writable') is-invalid @enderror"
                               id="is_writable" name="is_writable" value="1"
                               @checked(old('is_writable', $source->is_writable ?? false))>
                        <label class="custom-control-label" for="is_writable">Boleh ditulis</label>
                    </div>
                    @error('is_writable')<span class="text-danger small d-block mb-2">{{ $message }}</span>@enderror

                    <div class="alert alert-warning small mb-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Izin tulis membuka penambahan, perubahan, dan penghapusan
                        baris lewat form dinamis.</strong> Nyalakan hanya untuk tabel bisnis —
                        jangan untuk tabel yang dipakai sistem.
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active"
                               name="is_active" value="1" @checked(old('is_active', $source->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('data-sources.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Kolom yang Diblokir</h3>
                    @if ($suggested)
                        <div class="card-tools">
                            <button type="button" class="btn btn-xs btn-warning" id="btn-suggest">
                                <i class="fas fa-magic mr-1"></i> Blokir yang sensitif
                            </button>
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @if (! $columns)
                        <p class="text-muted mb-0">
                            Pilih tabel terlebih dahulu untuk melihat kolomnya.
                        </p>
                    @else
                        <p class="text-muted small">
                            Kolom yang dicentang tidak akan pernah terbaca engine — tidak muncul
                            sebagai field, tidak bisa dipakai kolom list, filter, maupun ekspresi,
                            dan tidak ikut terbawa saat baris dibaca.
                        </p>
                        @error('blocked_columns')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror
                        <div class="row">
                            @foreach ($columns as $column)
                                @php $isSensitive = in_array($column, $suggested, true); @endphp
                                <div class="col-md-4">
                                    <div class="custom-control custom-checkbox mb-1">
                                        <input type="checkbox" class="custom-control-input col-block"
                                               id="blk_{{ $column }}" name="blocked_columns[]"
                                               value="{{ $column }}"
                                               data-sensitive="{{ $isSensitive ? 1 : 0 }}"
                                               @checked(in_array($column, $blocked, true))>
                                        <label class="custom-control-label" for="blk_{{ $column }}">
                                            {{ $column }}
                                            @if ($isSensitive)
                                                <i class="fas fa-exclamation-triangle text-warning small"></i>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($suggested)
                    <div class="card-footer text-muted small">
                        Kolom bertanda <i class="fas fa-exclamation-triangle text-warning"></i>
                        terdeteksi sensitif berdasarkan namanya
                        (<code>{{ implode('</code>, <code>', $suggested) }}</code>).
                    </div>
                @endif
            </div>

            @if ($usage)
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Dipakai Oleh</h3></div>
                    <div class="card-body">
                        @foreach ($usage as $item)
                            <span class="badge badge-info mr-1">{{ $item }}</span>
                        @endforeach
                        <p class="text-muted small mt-2 mb-0">
                            Mematikan izin baca atau memblokir kolom akan langsung berpengaruh
                            pada semua yang tercantum di atas.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    // Kolom tabel baru bisa dibaca setelah tabelnya diketahui server, jadi
    // memilih tabel memuat ulang halaman dengan tabel itu terpilih.
    $('#table_name').on('change', function () {
        const table = $(this).val();
        if (table) window.location = '{{ route('data-sources.create') }}?table=' + encodeURIComponent(table);
    });

    $('#btn-suggest').on('click', function () {
        $('.col-block[data-sensitive="1"]').prop('checked', true);
    });
});
</script>
@endpush
