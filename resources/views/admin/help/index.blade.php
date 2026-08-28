@extends('layouts.adminlte.app')
@section('title', 'Bantuan')
@section('page-title', 'Basis Pengetahuan Bantuan')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center">
                <a href="{{ route('help-articles.create') }}" class="btn btn-primary btn-sm mr-3">
                    <i class="fas fa-plus mr-1"></i> Tambah Artikel
                </a>
                <span class="text-muted small">{{ $jumlah }} artikel menjawab pertanyaan di chatbot.</span>

                <form method="GET" class="form-inline ml-auto">
                    <select name="kategori" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">Semua kategori</option>
                        @foreach ($kategori as $k)
                            <option value="{{ $k }}" @selected(($filter['kategori'] ?? '') === $k)>{{ $k }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="cari" class="form-control form-control-sm mr-2" style="width:170px"
                           value="{{ $filter['cari'] ?? '' }}" placeholder="Cari…">
                    <button class="btn btn-default btn-sm"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="card-body p-0">
                @forelse ($artikel as $nama => $isi)
                    <div class="px-3 py-2 bg-light border-bottom">
                        <strong class="small text-uppercase">{{ $nama }}</strong>
                        <span class="text-muted small">— {{ $isi->count() }} artikel</span>
                    </div>
                    <table class="table table-sm table-striped mb-0">
                        <tbody>
                        @foreach ($isi as $a)
                            <tr>
                                <td>
                                    <div>{{ $a->question }}</div>
                                    <small class="text-muted">
                                        <code>{{ $a->code }}</code>
                                        @if ($a->keywords)
                                            &middot; {{ \Illuminate\Support\Str::limit($a->keywords, 70) }}
                                        @endif
                                    </small>
                                </td>
                                <td class="text-center" style="width:110px">
                                    @if ($a->is_featured)
                                        <span class="badge badge-info" title="Ditawarkan saat panel dibuka">Unggulan</span>
                                    @endif
                                    @unless ($a->is_active)
                                        <span class="badge badge-secondary">Nonaktif</span>
                                    @endunless
                                </td>
                                <td class="text-center" style="width:100px">
                                    <a href="{{ route('help-articles.edit', $a) }}" class="btn btn-xs btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('help-articles.destroy', $a) }}" class="d-inline"
                                          onsubmit="return confirm('Hapus artikel ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @empty
                    <p class="text-center text-muted py-4 mb-0">
                        Belum ada artikel. Jalankan <code>php artisan db:seed --class=HelpArticleSeeder</code>
                        untuk memuat isi bawaan.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{--
            Daftar inilah yang membuat basis pengetahuan bisa mengejar aplikasinya.
            Tanpa ini, satu-satunya cara tahu jawaban apa yang kurang adalah
            menunggu ada yang mengeluh.
        --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pertanyaan Tanpa Jawaban</h3>
            </div>
            <div class="card-body p-0">
                @forelse ($takTerjawab as $q)
                    <div class="px-3 py-2 border-bottom">
                        <div class="d-flex">
                            <div class="flex-grow-1">{{ $q->question }}</div>
                            @if ($q->jumlah > 1)
                                <span class="badge badge-warning align-self-start ml-2">{{ $q->jumlah }}×</span>
                            @endif
                        </div>
                        <a href="{{ route('help-articles.create', ['pertanyaan' => $q->question]) }}"
                           class="small">Buatkan jawabannya</a>
                    </div>
                @empty
                    <p class="text-center text-muted py-4 mb-0">
                        Belum ada pertanyaan yang tidak terjawab.
                    </p>
                @endforelse
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('help-articles.prune') }}" class="form-inline"
                      onsubmit="return confirm('Buang riwayat pertanyaan yang lebih tua dari itu?')">
                    @csrf
                    <label class="small text-muted mr-2 mb-0">Buang riwayat lebih tua dari</label>
                    <input type="number" name="days" value="90" min="1" max="3650"
                           class="form-control form-control-sm mr-2" style="width:80px">
                    <span class="small text-muted mr-2">hari</span>
                    <button class="btn btn-default btn-sm">Buang</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
