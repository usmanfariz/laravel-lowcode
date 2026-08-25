@extends('layouts.adminlte.app')
@section('title', 'Berkas Ekspor')
@section('page-title', 'Berkas Ekspor')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ekspor Saya</h3>
        <div class="card-tools d-flex align-items-center">
            <form method="POST" action="{{ route('exports.prune') }}" class="form-inline mr-2"
                  onsubmit="return confirm('Hapus permanen berkas ekspor yang lebih tua dari jumlah hari tersebut?')">
                @csrf
                <label class="small text-muted mr-2 mb-0">Buang lebih tua dari</label>
                <input type="number" name="days" value="7" min="1" max="365"
                       class="form-control form-control-sm mr-2" style="width:70px">
                <span class="small text-muted mr-2">hari</span>
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-broom mr-1"></i>Buang</button>
            </form>
            <button type="button" class="btn btn-sm btn-default" onclick="window.location.reload()">
                <i class="fas fa-sync mr-1"></i> Muat Ulang
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-sm mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Sumber</th><th style="width:80px">Format</th>
                    <th style="width:120px">Status</th>
                    <th class="text-right" style="width:100px">Baris</th>
                    <th class="text-right" style="width:100px">Ukuran</th>
                    <th style="width:150px">Diminta</th>
                    <th style="width:90px">Durasi</th>
                    <th style="width:120px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td class="text-muted">{{ $job->id }}</td>
                    <td>
                        {{ $job->title }}
                        <div class="text-muted small">
                            {{ $job->source_type }} &middot; <code>{{ $job->source_code }}</code>
                        </div>
                        @if ($job->status === 'failed' && $job->error)
                            <div class="text-danger small mt-1">{{ $job->error }}</div>
                        @endif
                    </td>
                    <td><span class="badge badge-light border">{{ $job->format }}</span></td>
                    <td>
                        @switch($job->status)
                            @case('queued')
                                <span class="badge badge-secondary"><i class="fas fa-clock mr-1"></i>Antre</span>
                                @break
                            @case('processing')
                                <span class="badge badge-info"><i class="fas fa-spinner fa-spin mr-1"></i>Diproses</span>
                                @break
                            @case('done')
                                <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Selesai</span>
                                @break
                            @default
                                <span class="badge badge-danger"><i class="fas fa-times mr-1"></i>Gagal</span>
                        @endswitch
                    </td>
                    <td class="text-right">
                        {{ $job->row_count !== null ? number_format($job->row_count, 0, ',', '.') : '—' }}
                    </td>
                    <td class="text-right small">
                        {{ $job->file_size ? number_format($job->file_size / 1024, 0, ',', '.').' KB' : '—' }}
                    </td>
                    <td class="small">{{ $job->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="small">
                        {{ $job->durationSeconds() !== null ? $job->durationSeconds().' dtk' : '—' }}
                    </td>
                    <td class="text-center">
                        @if ($job->isDownloadable())
                            <a href="{{ route('exports.download', $job) }}" class="btn btn-xs btn-success">
                                <i class="fas fa-download"></i>
                            </a>
                        @endif
                        @if ($job->status === 'failed')
                            <form method="POST" action="{{ route('exports.retry', $job) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-xs btn-warning" title="Coba lagi">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('exports.destroy', $job) }}" class="d-inline"
                              onsubmit="return confirm('Hapus berkas ekspor ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">
                    Belum ada ekspor terantre. Ekspor yang datanya sedikit langsung diunduh
                    tanpa melewati halaman ini.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($jobs->whereIn('status', ['queued', 'processing'])->isNotEmpty())
        <div class="card-footer">
            <i class="fas fa-info-circle mr-1 text-info"></i>
            Ada ekspor yang sedang dikerjakan. Halaman ini menyegar sendiri setiap 10 detik.
            <strong>Pastikan queue worker berjalan</strong> —
            <code>php artisan queue:work</code>.
        </div>
    @endif
</div>

<div class="callout callout-info">
    <p class="mb-0 small">
        Berkas ekspor dibuang otomatis setelah <strong>7 hari</strong> lewat tugas
        terjadwal <code>exports:prune</code>. Agar berjalan, server perlu satu entri cron:
        <code>* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run</code>
    </p>
</div>
@endsection

@push('scripts')
@if ($jobs->whereIn('status', ['queued', 'processing'])->isNotEmpty())
<script>
    // Menyegar hanya selama masih ada pekerjaan berjalan, supaya halaman
    // tidak terus memuat ulang saat semuanya sudah selesai.
    setTimeout(() => window.location.reload(), 10000);
</script>
@endif
@endpush
