@extends('layouts.adminlte.app')
@section('title', 'Field — '.$form->name)
@section('page-title', 'Field: '.$form->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('builder.forms.index') }}">Form Builder</a></li>
    <li class="breadcrumb-item active">{{ $form->code }}</li>
@endsection

@section('content')
@include('builder._formnav')

@if ($details->isNotEmpty())
    <div class="card card-outline card-secondary">
        <div class="card-body py-2">
            <span class="small text-muted mr-2">Lingkup field:</span>
            <a href="{{ route('builder.fields.index', $form) }}"
               class="btn btn-xs btn-{{ $detail ? 'default' : 'primary' }}">
                Form induk ({{ $form->table_name }})
            </a>
            @foreach ($details as $d)
                <a href="{{ route('builder.fields.index', [$form, 'detail' => $d->id]) }}"
                   class="btn btn-xs btn-{{ $detail?->id === $d->id ? 'primary' : 'default' }}">
                    {{ $d->title }} ({{ $d->table_name }})
                </a>
            @endforeach
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <a href="{{ route('builder.fields.create', [$form, 'detail' => $detail?->id]) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Tambah Field{{ $detail ? ' Detail' : '' }}
        </a>
        <span class="text-muted small ml-2">
            <i class="fas fa-arrows-alt-v mr-1"></i> Seret baris untuk mengubah urutan.
        </span>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" id="tbl-fields">
            <thead>
                <tr>
                    <th style="width:34px"></th>
                    <th style="width:50px">#</th>
                    <th>Kolom</th><th>Label</th><th>Jenis</th><th>Sumber Opsi</th>
                    <th class="text-center">Wajib</th><th class="text-center">Lebar</th>
                    <th class="text-center">Status</th><th style="width:90px" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody id="sortable">
            @forelse ($fields as $field)
                <tr data-id="{{ $field->id }}">
                    <td class="text-center text-muted handle" style="cursor:grab">
                        <i class="fas fa-grip-vertical"></i>
                    </td>
                    <td class="text-muted small order-no">{{ $field->order_no }}</td>
                    <td><code>{{ $field->field_name }}</code></td>
                    <td>{{ $field->label }}</td>
                    <td><span class="badge badge-light border">{{ $field->input_type }}</span></td>
                    <td class="small">
                        @switch($field->data_source_type)
                            @case('table')
                                <i class="fas fa-link text-muted mr-1"></i>{{ $field->data_source }}.{{ $field->label_field }}
                                @break
                            @case('enum') <span class="text-muted">enum kolom</span> @break
                            @case('static') <span class="text-muted">opsi statis</span> @break
                            @default <span class="text-muted">—</span>
                        @endswitch
                    </td>
                    <td class="text-center">
                        @if ($field->is_required)<i class="fas fa-check text-success"></i>@endif
                    </td>
                    <td class="text-center small">{{ $field->width }}/12</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $field->is_active ? 'success' : 'secondary' }}">
                            {{ $field->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('builder.fields.edit', [$form, $field]) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" action="{{ route('builder.fields.destroy', [$form, $field]) }}"
                              class="d-inline" onsubmit="return confirm('Hapus field ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-3">Belum ada field.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($unmapped)
        <div class="card-footer">
            <strong class="small">Kolom tabel yang belum punya field:</strong>
            @foreach ($unmapped as $column)
                <a href="{{ route('builder.fields.create', [$form, 'detail' => $detail?->id]) }}?field_name={{ $column }}"
                   class="badge badge-warning">{{ $column }}</a>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
$(function () {
    const el = document.getElementById('sortable');
    if (!el || !el.querySelector('tr[data-id]')) return;

    Sortable.create(el, {
        handle: '.handle',
        animation: 150,
        onEnd: function () {
            const order = $('#sortable tr[data-id]').map(function () { return $(this).data('id'); }).get();

            // Nomor urut di layar disegarkan lebih dulu agar perubahan terlihat
            // langsung, tanpa menunggu balasan server.
            $('#sortable tr[data-id] .order-no').each(function (i) { $(this).text(i + 1); });

            $.post('{{ route('builder.fields.reorder', [$form, 'detail' => $detail?->id]) }}', { order: order })
                .fail(() => alert('Gagal menyimpan urutan. Muat ulang halaman.'));
        },
    });
});
</script>
@endpush
