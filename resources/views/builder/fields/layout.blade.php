@extends('layouts.adminlte.app')
@section('title', 'Tata Letak — '.$form->name)
@section('page-title', 'Tata Letak: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item active">{{ $form->code }}</li>
@endsection

@push('styles')
<style>
    .canvas { background: #f4f6f9; border: 2px dashed #ced4da; border-radius: 6px; min-height: 220px; padding: 10px; }
    .canvas .block { padding: 4px; }
    .block-inner {
        background: #fff; border: 1px solid #ced4da; border-radius: 4px;
        padding: 8px 10px; height: 100%; position: relative; cursor: grab;
    }
    .block-inner:hover { border-color: #007bff; box-shadow: 0 0 0 2px rgba(0,123,255,.12); }
    .block.sortable-ghost .block-inner { opacity: .4; border-style: dashed; }
    .block-label { font-size: 13px; font-weight: 600; }
    .block-meta { font-size: 11px; color: #6c757d; }
    .block-size { position: absolute; top: 4px; right: 6px; }
    .block-size .btn { padding: 0 4px; font-size: 10px; line-height: 1.4; }
    .block-inactive .block-inner { background: #f8f9fa; opacity: .65; }
    .ruler { display: flex; gap: 0; margin-bottom: 4px; }
    .ruler div {
        flex: 1; text-align: center; font-size: 10px; color: #adb5bd;
        border-left: 1px dashed #dee2e6; padding: 2px 0;
    }
    .ruler div:first-child { border-left: none; }
</style>
@endpush

@section('content')
@include('builder._formnav')

@if ($details->isNotEmpty())
    <div class="card card-outline card-secondary">
        <div class="card-body py-2">
            <span class="small text-muted mr-2">Lingkup:</span>
            <a href="{{ route('builder.fields.layout', $form) }}"
               class="btn btn-xs btn-{{ $detail ? 'default' : 'primary' }}">
                Form induk
            </a>
            @foreach ($details as $d)
                <a href="{{ route('builder.fields.layout', [$form, 'detail' => $d->id]) }}"
                   class="btn btn-xs btn-{{ $detail?->id === $d->id ? 'primary' : 'default' }}">
                    {{ $d->title }}
                </a>
            @endforeach
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Kanvas</h3>
        <div class="card-tools">
            <span class="text-muted small mr-2" id="status"></span>
            <button type="button" class="btn btn-sm btn-default" id="btn-reset">Batalkan Perubahan</button>
            <button type="button" class="btn btn-sm btn-primary" id="btn-save">
                <i class="fas fa-save mr-1"></i> Simpan Tata Letak
            </button>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Seret blok untuk mengatur urutan, dan pakai tombol
            <span class="badge badge-light border">&minus;</span>
            <span class="badge badge-light border">+</span> untuk mengubah lebarnya.
            Grid ini 12 kolom — sama persis dengan yang dipakai form sungguhan.
        </p>

        <div class="ruler">
            @for ($i = 1; $i <= 12; $i++)<div>{{ $i }}</div>@endfor
        </div>

        <div class="canvas row no-gutters" id="canvas">
            @forelse ($fields as $field)
                <div class="block col-md-{{ max(1, min(12, $field->width ?: 6)) }} {{ $field->is_active ? '' : 'block-inactive' }}"
                     data-id="{{ $field->id }}" data-width="{{ max(1, min(12, $field->width ?: 6)) }}">
                    <div class="block-inner">
                        <div class="block-size btn-group">
                            <button type="button" class="btn btn-outline-secondary js-narrow" title="Persempit">&minus;</button>
                            <button type="button" class="btn btn-outline-secondary js-widen" title="Perlebar">+</button>
                        </div>
                        <div class="block-label">
                            {{ $field->label }}
                            @if ($field->is_required)<span class="text-danger">*</span>@endif
                        </div>
                        <div class="block-meta">
                            <code>{{ $field->field_name }}</code>
                            &middot; {{ $field->input_type }}
                            &middot; <span class="w-badge">{{ max(1, min(12, $field->width ?: 6)) }}</span>/12
                            @unless ($field->is_active)&middot; <em>nonaktif</em>@endunless
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">
                    Belum ada field. Tambahkan lewat tab <strong>Field</strong> terlebih dahulu.
                </div>
            @endforelse
        </div>
    </div>
    <div class="card-footer text-muted small">
        Halaman ini hanya menyunting <code>order_no</code> dan <code>width</code>.
        Sifat field lainnya diubah lewat tab <strong>Field</strong>.
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Pratinjau Baris</h3></div>
    <div class="card-body">
        <p class="text-muted small">
            Perkiraan pembagian baris berdasarkan lebar sekarang. Field yang tidak muat
            akan turun ke baris berikutnya, sama seperti di form sungguhan.
        </p>
        <div id="preview"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
$(function () {
    const $canvas = $('#canvas');
    if (!$canvas.find('.block').length) return;

    // Keadaan awal disimpan agar tombol batal bisa mengembalikannya tanpa
    // memuat ulang halaman.
    const initial = snapshot();
    let dirty = false;

    function snapshot() {
        return $canvas.find('.block').map(function () {
            return { id: $(this).data('id'), width: $(this).data('width') };
        }).get();
    }

    function setWidth($block, width) {
        width = Math.max(1, Math.min(12, width));
        $block.removeClass(function (i, cls) {
            return (cls.match(/col-md-\d+/g) || []).join(' ');
        }).addClass('col-md-' + width).data('width', width);
        $block.find('.w-badge').text(width);
        markDirty();
    }

    function markDirty() {
        dirty = true;
        $('#status').text('ada perubahan belum disimpan').addClass('text-warning');
        renderPreview();
    }

    // Pembagian baris dihitung dengan aturan yang sama seperti Bootstrap:
    // lebar diakumulasi sampai melewati 12, lalu pindah baris.
    function renderPreview() {
        const rows = [];
        let row = [], used = 0;

        $canvas.find('.block').each(function () {
            const w = $(this).data('width');
            const label = $(this).find('.block-label').text().trim();
            if (used + w > 12) { rows.push(row); row = []; used = 0; }
            row.push({ label: label, width: w });
            used += w;
        });
        if (row.length) rows.push(row);

        const $out = $('#preview').empty();
        rows.forEach((r, i) => {
            const $row = $('<div class="row no-gutters mb-1">');
            r.forEach(c => {
                $('<div>').addClass('col-md-' + c.width).append(
                    $('<div class="border rounded px-2 py-1 mr-1 bg-light small text-truncate">')
                        .text(c.label + ' (' + c.width + ')')
                ).appendTo($row);
            });
            $out.append($('<div class="d-flex align-items-center">').append(
                $('<span class="text-muted small mr-2" style="width:52px">').text('baris ' + (i + 1)),
                $('<div class="flex-grow-1">').append($row)
            ));
        });
    }

    Sortable.create($canvas[0], {
        animation: 150,
        ghostClass: 'sortable-ghost',
        // Tombol lebar tidak boleh memicu seret.
        filter: '.block-size, .block-size *',
        preventOnFilter: false,
        onEnd: markDirty,
    });

    $canvas.on('click', '.js-widen', function () {
        const $b = $(this).closest('.block');
        setWidth($b, $b.data('width') + 1);
    });

    $canvas.on('click', '.js-narrow', function () {
        const $b = $(this).closest('.block');
        setWidth($b, $b.data('width') - 1);
    });

    $('#btn-save').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        $.post('{{ route('builder.fields.layout.save', [$form, 'detail' => $detail?->id]) }}',
            { items: snapshot() })
            .done(() => {
                dirty = false;
                $('#status').text('tersimpan').removeClass('text-warning').addClass('text-success');
            })
            .fail(() => alert('Gagal menyimpan tata letak. Muat ulang halaman.'))
            .always(() => $btn.prop('disabled', false));
    });

    $('#btn-reset').on('click', function () {
        if (dirty && !confirm('Batalkan semua perubahan yang belum disimpan?')) return;
        window.location.reload();
    });

    // Peringatan bila menutup halaman dengan perubahan menggantung.
    $(window).on('beforeunload', function () {
        if (dirty) return 'Ada perubahan tata letak yang belum disimpan.';
    });

    renderPreview();
});
</script>
@endpush
