@extends('layouts.adminlte.app')
@section('title', $article->exists ? 'Ubah Artikel Bantuan' : 'Tambah Artikel Bantuan')
@section('page-title', $article->exists ? 'Ubah Artikel Bantuan' : 'Tambah Artikel Bantuan')

@section('content')
<form method="POST" action="{{ $article->exists ? route('help-articles.update', $article) : route('help-articles.store') }}">
    @csrf
    @if ($article->exists) @method('PUT') @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Pertanyaan <span class="text-danger">*</span></label>
                        <input type="text" name="question" class="form-control @error('question') is-invalid @enderror"
                               value="{{ old('question', $article->question) }}" required maxlength="255"
                               placeholder="mis. Bagaimana cara mengekspor data ke Excel?">
                        @error('question')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Tulis sebagaimana pengguna akan menanyakannya, bukan sebagai judul bab.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Jawaban <span class="text-danger">*</span></label>
                        <textarea name="answer" rows="12" required maxlength="10000"
                                  class="form-control @error('answer') is-invalid @enderror">{{ old('answer', $article->answer) }}</textarea>
                        @error('answer')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Teks biasa. Penanda yang dikenali: <code>**tebal**</code>,
                            <code>`kode`</code>, baris berawalan <code>-</code> menjadi butir, dan
                            <code>```</code> membungkus blok perintah. HTML tidak diproses.
                            Jawaban pendek lebih terbaca di balon chat — sisanya tautkan ke halamannya.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Kata Kunci</label>
                        <input type="text" name="keywords" class="form-control @error('keywords') is-invalid @enderror"
                               value="{{ old('keywords', $article->keywords) }}" maxlength="500"
                               placeholder="ekspor, excel, csv, download data, unduh">
                        @error('keywords')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Dipisah koma. Isi dengan istilah yang tidak muncul di pertanyaan:
                            sinonim, singkatan, padanan Inggris, dan bunyi pesan galat yang
                            biasanya disalin-tempel pengguna. Ini yang paling menentukan
                            apakah artikel ini ketemu.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="form-group">
                        <label>Kode <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $article->code) }}" required maxlength="100"
                               placeholder="mis. ekspor.excel">
                        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">Huruf kecil, angka, titik, dan garis.</small>
                    </div>

                    <div class="form-group">
                        <label>Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control @error('category') is-invalid @enderror"
                               value="{{ old('category', $article->category) }}" required maxlength="100"
                               list="daftar-kategori">
                        <datalist id="daftar-kategori">
                            @foreach ($kategori as $k)<option value="{{ $k }}">@endforeach
                        </datalist>
                        @error('category')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">Dipakai mengelompokkan daftar topik.</small>
                    </div>

                    <div class="form-group">
                        <label>Route Tujuan</label>
                        <input type="text" name="link_route" class="form-control @error('link_route') is-invalid @enderror"
                               value="{{ old('link_route', $article->link_route) }}" maxlength="150"
                               placeholder="mis. settings.index">
                        @error('link_route')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="form-text text-muted">
                            Nama route Laravel, atau alamat yang diawali <code>/</code>.
                            Route yang belum ada tidak menggagalkan apa pun — tombolnya
                            sekadar tidak tampil.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Label Tombol</label>
                        <input type="text" name="link_label" class="form-control"
                               value="{{ old('link_label', $article->link_label) }}" maxlength="100"
                               placeholder="Buka halaman">
                    </div>

                    <div class="form-group">
                        <label>Izin untuk Tombol</label>
                        <select name="permission_code" class="form-control select2">
                            <option value="">— tombol tampil untuk semua —</option>
                            @foreach ($permissions as $code)
                                <option value="{{ $code }}" @selected(old('permission_code', $article->permission_code) === $code)>
                                    {{ $code }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Membatasi tombolnya saja. Jawabannya tetap terbaca semua orang —
                            menyembunyikan penjelasan cara kerja aplikasi tidak melindungi
                            apa pun.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Urutan <span class="text-danger">*</span></label>
                        <input type="number" name="order_no" class="form-control @error('order_no') is-invalid @enderror"
                               value="{{ old('order_no', $article->order_no ?? 0) }}" required min="0" max="9999">
                        @error('order_no')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    <div class="custom-control custom-switch mb-2">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1"
                               @checked(old('is_featured', $article->is_featured))>
                        <label class="custom-control-label" for="is_featured">Tawarkan saat panel dibuka</label>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $article->is_active ?? true))>
                        <label class="custom-control-label" for="is_active">Aktif</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="{{ route('help-articles.index') }}" class="btn btn-default">Batal</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.select2').select2({ width: '100%' });
});
</script>
@endpush
