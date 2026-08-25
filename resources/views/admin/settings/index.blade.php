@extends('layouts.adminlte.app')
@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan Aplikasi')

@section('content')
@if ($groups->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-sliders-h fa-3x text-muted mb-3"></i>
            <h5>Belum ada pengaturan</h5>
            <p class="text-muted mb-0">
                Jalankan <code>php artisan db:seed --class=MetadataSeeder</code> untuk memasang
                pengaturan bawaan.
            </p>
        </div>
    </div>
@else
<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <input type="hidden" name="tab" id="tab-aktif" value="{{ $groups->keys()->first() }}">

    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                @foreach ($groups as $group => $rows)
                    <li class="nav-item">
                        <a class="nav-link @if ($loop->first) active @endif" id="tab-{{ $group }}"
                           data-toggle="pill" href="#panel-{{ $group }}" data-group="{{ $group }}" role="tab">
                            <i class="{{ $rows->first()->groupIcon() }} mr-1"></i>
                            {{ $rows->first()->groupLabel() }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">
                @foreach ($groups as $group => $rows)
                    <div class="tab-pane fade @if ($loop->first) show active @endif" id="panel-{{ $group }}" role="tabpanel">
                        <div class="row">
                            @foreach ($rows as $setting)
                                @include('admin.settings.field', ['setting' => $setting])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
            <span class="text-muted small ml-2">
                Perubahan langsung berlaku di seluruh halaman — cache pengaturan dibuang otomatis.
            </span>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
$(function () {
    // Tab yang sedang dibuka ditulis ke fragment URL, lalu dikirim balik lewat
    // input tersembunyi — supaya setelah menyimpan tidak terlempar ke tab pertama.
    function aktifkan(group) {
        const tab = $('#tab-' + group);
        if (tab.length) {
            tab.tab('show');
            $('#tab-aktif').val(group);
        }
    }

    if (window.location.hash) {
        aktifkan(window.location.hash.substring(1));
    }

    $('a[data-toggle="pill"]').on('shown.bs.tab', function () {
        const group = $(this).data('group');
        $('#tab-aktif').val(group);
        history.replaceState(null, '', '#' + group);
    });
});
</script>
@endpush
