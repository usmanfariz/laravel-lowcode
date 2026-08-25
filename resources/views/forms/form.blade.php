@extends('layouts.adminlte.app')
@section('title', $form->title ?: $form->name)
@section('page-title', ($id ? 'Ubah ' : 'Tambah ').($form->title ?: $form->name))

@section('content')
<form method="POST"
      action="{{ $id ? url("forms/{$form->code}/{$id}") : url("forms/{$form->code}") }}"
      enctype="multipart/form-data">
    @csrf
    @if ($id)
        @method('PUT')
        {{-- Penanda versi: dibandingkan saat menyimpan agar perubahan orang
             lain tidak tertimpa diam-diam. --}}
        <input type="hidden" name="__version" value="{{ $row['updated_at'] ?? '' }}">
    @endif

    <div class="card">
        @if ($form->description)
            <div class="card-header"><p class="text-muted mb-0">{{ $form->description }}</p></div>
        @endif

        <div class="card-body">
            <div class="row">
                @foreach ($form->fields as $field)
                    <x-form.field
                        :field="$field"
                        :value="$renderer->valueFor($field, $row)"
                        :options="$renderer->optionsFor($field)" />
                @endforeach
            </div>
        </div>

        @foreach ($form->details as $detail)
            <div class="card-body border-top">
                <h5>{{ $detail->title }}</h5>
                <table class="table table-sm table-bordered" data-detail="{{ $detail->code }}">
                    <thead>
                        <tr>
                            @foreach ($detail->fields as $field)
                                <th>{{ $field->label }}</th>
                            @endforeach
                            @if ($detail->allow_delete)<th style="width:40px"></th>@endif
                        </tr>
                    </thead>
                    @php $rows = $detailRows[$detail->code] ?? []; @endphp
                    <tbody>
                        @foreach (($rows ?: [[]]) as $rowIndex => $detailRow)
                        <tr>
                            @foreach ($detail->fields as $field)
                                <td>
                                    <x-dynamic-component
                                        :component="$field->component()"
                                        :field="$field"
                                        :name="'detail['.$detail->code.']['.$rowIndex.']['.$field->field_name.']'"
                                        :id="'d_'.$detail->code.'_'.$rowIndex.'_'.$field->field_name"
                                        :value="$renderer->valueFor($field, $detailRow)"
                                        :options="$renderer->optionsFor($field)"
                                        :error-key="'detail.'.$detail->code.'.'.$rowIndex.'.'.$field->field_name" />
                                </td>
                            @endforeach
                            @if ($detail->allow_delete)
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-danger js-detail-remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($detail->allow_add)
                    <button type="button" class="btn btn-sm btn-default js-detail-add"
                            data-detail="{{ $detail->code }}">
                        <i class="fas fa-plus mr-1"></i> Tambah Baris
                    </button>
                @endif
            </div>
        @endforeach

        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
            <a href="{{ url("forms/{$form->code}") }}" class="btn btn-default">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-select2').select2({ width: '100%' });

    // Select bertipe ajax memuat opsinya saat diketik, bukan di awal.
    $('select[data-ajax-field]').each(function () {
        const $el = $(this);
        $el.select2({
            width: '100%',
            ajax: {
                url: '{{ url("forms/{$form->code}/options") }}/' + $el.data('ajax-field'),
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
            },
        });
    });

    // Select bergantung: mengganti induk memuat ulang opsi anaknya.
    $('select[data-depends-on]').each(function () {
        const $child = $(this);
        const $parent = $('[name="' + $child.data('depends-on') + '"]');

        const reload = () => {
            const parentValue = $parent.val();
            $child.empty().append('<option value="">— pilih —</option>');
            if (!parentValue) return;

            $.getJSON('{{ url("forms/{$form->code}/options") }}/' + $child.data('field-id'),
                { parent: parentValue },
                data => data.results.forEach(o =>
                    $child.append($('<option>').val(o.id).text(o.text))));
        };

        $parent.on('change', reload);
    });

    // Baris detail baru disalin dari baris pertama, dengan indeks diperbarui.
    $('.js-detail-add').on('click', function () {
        const code = $(this).data('detail');
        const $body = $('table[data-detail="' + code + '"] tbody');
        const index = $body.children('tr').length;
        const $row = $body.children('tr').first().clone();

        $row.find('input, select, textarea').each(function () {
            const name = $(this).attr('name');
            if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
            $(this).removeAttr('id').val('');
        });
        $row.find('.select2-container').remove();
        $body.append($row);
        $row.find('.js-select2').select2({ width: '100%' });
    });

    $(document).on('click', '.js-detail-remove', function () {
        const $body = $(this).closest('tbody');
        if ($body.children('tr').length > 1) $(this).closest('tr').remove();
    });
});
</script>
@endpush
