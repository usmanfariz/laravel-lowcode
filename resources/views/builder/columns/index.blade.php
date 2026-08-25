@extends('layouts.adminlte.app')
@section('title', 'Kolom List — '.$form->name)
@section('page-title', 'Kolom Halaman List: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item"><a href="{{ route('builder.fields.index', $form) }}">{{ $form->code }}</a></li>
    <li class="breadcrumb-item active">Kolom List</li>
@endsection

@section('content')
@include('builder._formnav')

<div class="card">
    <div class="card-header">
        <a href="{{ route('builder.columns.create', $form) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Tambah Kolom
        </a>
        <form method="POST" action="{{ route('builder.columns.reset', $form) }}" class="d-inline"
              onsubmit="return confirm('Susun ulang kolom list dari field form? Susunan sekarang akan diganti.')">
            @csrf
            <button class="btn btn-warning btn-sm"><i class="fas fa-sync mr-1"></i> Susun Ulang dari Field</button>
        </form>
        <span class="text-muted small ml-2">
            <i class="fas fa-arrows-alt-v mr-1"></i> Seret untuk mengubah urutan.
        </span>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:34px"></th><th style="width:50px">#</th>
                    <th>Label</th><th>Sumber</th><th>Format</th>
                    <th class="text-center">Rata</th><th class="text-center">Tampil</th>
                    <th class="text-center">Cari</th><th class="text-center">Urut</th>
                    <th style="width:90px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody id="sortable">
            @forelse ($columns as $column)
                <tr data-id="{{ $column->id }}">
                    <td class="text-center text-muted handle" style="cursor:grab"><i class="fas fa-grip-vertical"></i></td>
                    <td class="text-muted small order-no">{{ $column->order_no }}</td>
                    <td>{{ $column->label }}</td>
                    <td class="small">
                        @switch($column->source_type)
                            @case('relation')
                                <i class="fas fa-link text-muted mr-1"></i>
                                <code>{{ $column->column_name }}</code> →
                                {{ $column->relation_table }}.{{ $column->relation_label }}
                                @break
                            @case('expression')
                                <i class="fas fa-superscript text-warning mr-1"></i>
                                <code>{{ $column->expression }}</code>
                                @break
                            @default
                                <code>{{ $column->column_name }}</code>
                        @endswitch
                    </td>
                    <td><span class="badge badge-light border">{{ $column->format }}</span></td>
                    <td class="text-center small">{{ $column->align }}</td>
                    @foreach (['is_visible', 'is_searchable', 'is_sortable'] as $flag)
                        <td class="text-center">
                            @if ($column->$flag)
                                <i class="fas fa-check text-success"></i>
                            @else
                                <i class="fas fa-minus text-muted"></i>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center">
                        <a href="{{ route('builder.columns.edit', [$form, $column]) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" action="{{ route('builder.columns.destroy', [$form, $column]) }}"
                              class="d-inline" onsubmit="return confirm('Hapus kolom ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-3">
                    Belum ada kolom list. Halaman index akan memakai 6 field pertama sebagai cadangan.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
$(function () {
    const el = document.getElementById('sortable');
    if (!el || !el.querySelector('tr[data-id]')) return;

    Sortable.create(el, {
        handle: '.handle', animation: 150,
        onEnd: function () {
            const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();
            $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });
            $.post('{{ route('builder.columns.reorder', $form) }}', { order: order })
                .fail(() => alert('Gagal menyimpan urutan. Muat ulang halaman.'));
        },
    });
});
</script>
@endpush
