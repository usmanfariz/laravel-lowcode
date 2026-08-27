@extends('layouts.adminlte.app')
@section('title', 'Pengaturan — '.$form->name)
@section('page-title', 'Pengaturan Form: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item active">{{ $form->code }}</li>
@endsection

@section('content')
@include('builder._formnav')

<div class="row">
    <div class="col-md-8">
        <form method="POST" action="{{ route('builder.forms.update', $form) }}">
            @csrf @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Identitas</h3>
                    <div class="card-tools">
                        <a href="{{ route('builder.fields.index', $form) }}" class="btn btn-xs btn-primary">
                            <i class="fas fa-list mr-1"></i> Kelola Field
                        </a>
                        <a href="{{ route('builder.columns.index', $form) }}" class="btn btn-xs btn-secondary">
                            <i class="fas fa-columns mr-1"></i> Kolom List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Kode</label>
                            <input type="text" class="form-control" value="{{ $form->code }}" disabled>
                            <small class="form-text text-muted">Tidak dapat diubah — dipakai di URL dan menu.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Tabel</label>
                            <input type="text" class="form-control" value="{{ $form->table_name }}" disabled>
                            <small class="form-text text-muted">Ganti tabel berarti form baru.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Primary Key <span class="text-danger">*</span></label>
                            <select name="primary_key" class="form-control">
                                @foreach ($columns as $column)
                                    <option value="{{ $column }}" @selected(old('primary_key', $form->primary_key) === $column)>
                                        {{ $column }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $form->name) }}" required>
                            @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Judul Halaman</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $form->title) }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $form->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Penguncian</h3></div>
                <div class="card-body">
                    <p class="text-muted small">
                        Baris yang memenuhi kondisi ini tidak bisa lagi diubah atau dihapus,
                        termasuk lewat URL langsung. Kosongkan kolomnya untuk mematikan penguncian.
                    </p>

                    @include('builder._condition', [
                        'prefix' => 'lock',
                        'columns' => $columns,
                        'condition' => $form->lock_condition,
                        'judul' => 'Kunci baris bila',
                        'bantuan' => 'Contoh: kolom "status" bernilai "posted".',
                    ])

                    <div class="form-group mb-0">
                        <label>Pesan saat ditolak</label>
                        <input type="text" name="lock_message"
                               class="form-control @error('lock_message') is-invalid @enderror"
                               value="{{ old('lock_message', $form->lock_message) }}"
                               placeholder="Nota yang sudah diposting tidak dapat diubah.">
                        @error('lock_message')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Dikosongkan berarti memakai pesan bawaan.
                        </small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Perilaku</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Tipe Form</label>
                            <select name="type" class="form-control">
                                @foreach (['single' => 'Tunggal', 'master_detail' => 'Master-Detail', 'wizard' => 'Wizard'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('type', $form->type) === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Jumlah Kolom</label>
                            <input type="number" name="layout_columns" class="form-control" min="1" max="4"
                                   value="{{ old('layout_columns', $form->layout_columns ?? 2) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Baris per Halaman</label>
                            <input type="number" name="per_page" class="form-control" min="5" max="200"
                                   value="{{ old('per_page', $form->per_page ?? 25) }}">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Prefix Izin</label>
                            <input type="text" name="permission_prefix"
                                   class="form-control @error('permission_prefix') is-invalid @enderror"
                                   value="{{ old('permission_prefix', $form->permission_prefix) }}">
                            @error('permission_prefix')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Urut Berdasarkan</label>
                            <select name="default_order_column" class="form-control">
                                <option value="">— bawaan —</option>
                                @foreach ($columns as $column)
                                    <option value="{{ $column }}" @selected(old('default_order_column', $form->default_order_column) === $column)>
                                        {{ $column }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Arah</label>
                            <select name="default_order_direction" class="form-control">
                                <option value="asc" @selected(old('default_order_direction', $form->default_order_direction) === 'asc')>A→Z</option>
                                <option value="desc" @selected(old('default_order_direction', $form->default_order_direction) === 'desc')>Z→A</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Kolom Scope</label>
                            <select name="scope_column" class="form-control">
                                <option value="">— tanpa pembatasan per baris —</option>
                                @foreach ($columns as $column)
                                    <option value="{{ $column }}" @selected(old('scope_column', $form->scope_column) === $column)>
                                        {{ $column }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Dibandingkan dengan <code>users.scope_value</code>.
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <strong class="small d-block mb-2">Aksi yang diizinkan</strong>
                            @foreach ([
                                'allow_create' => 'Tambah', 'allow_edit' => 'Ubah', 'allow_delete' => 'Hapus',
                                'allow_export' => 'Ekspor', 'allow_print' => 'Cetak',
                            ] as $name => $label)
                                <div class="custom-control custom-switch mb-1">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                           name="{{ $name }}" value="1" @checked(old($name, $form->$name))>
                                    <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-6">
                            <strong class="small d-block mb-2">Kolom khusus</strong>
                            @foreach ([
                                'use_soft_delete' => 'Pakai soft delete (deleted_at)',
                                'use_audit_column' => 'Isi kolom audit (created_by / updated_by)',
                                'is_active' => 'Form aktif',
                            ] as $name => $label)
                                <div class="custom-control custom-switch mb-1">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input type="checkbox" class="custom-control-input" id="{{ $name }}"
                                           name="{{ $name }}" value="1" @checked(old($name, $form->$name))>
                                    <label class="custom-control-label" for="{{ $name }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-group">
                        <label class="small">Catatan perubahan (disimpan di riwayat versi)</label>
                        <input type="text" name="note" class="form-control form-control-sm"
                               placeholder="mis. tambah kolom scope untuk pembatasan cabang">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('builder.forms.index') }}" class="btn btn-default">Kembali</a>
                </div>
            </div>
        </form>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Hook Simpan</h3></div>
            <div class="card-body">
                @if ($hooks === [])
                    <p class="text-muted small mb-0">
                        Tidak ada kode tambahan yang berjalan saat form ini menyimpan.
                        Hook dipasang lewat <code>config/lowcode.php</code>.
                    </p>
                @else
                    <p class="text-muted small">
                        Kode berikut ikut berjalan saat form ini menulis data, di dalam
                        transaksi yang sama:
                    </p>
                    <ul class="list-unstyled small mb-2">
                        @foreach ($hooks as $hook)
                            <li class="mb-1"><i class="fas fa-code text-muted mr-1"></i> <code>{{ $hook }}</code></li>
                        @endforeach
                    </ul>
                    <p class="text-muted small mb-0">
                        Dipasang di <code>config/lowcode.php</code>, bukan dari layar ini —
                        aturan bisnis yang wajib jalan tidak seharusnya bisa dimatikan lewat admin.
                    </p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Riwayat Versi</h3></div>
            <div class="card-body p-0" style="max-height:520px; overflow-y:auto">
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
                                <form method="POST"
                                      action="{{ route('builder.forms.restore', [$form, $version->version]) }}"
                                      onsubmit="return confirm('Kembalikan definisi form ke versi {{ $version->version }}? Keadaan sekarang akan disimpan sebagai versi baru.')">
                                    @csrf
                                    <button class="btn btn-xs btn-warning" title="Kembalikan">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
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
@endsection
