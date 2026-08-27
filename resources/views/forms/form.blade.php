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
            @php
                // Baris total hanya muncul bila detailnya menyalakannya DAN ada
                // kolom yang ditandai untuk dijumlahkan.
                $kolomTotal = $detail->show_total_row
                    ? $detail->fields->where('show_total', true)
                    : collect();
            @endphp

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

                    @if ($kolomTotal->isNotEmpty())
                        <tfoot>
                            <tr>
                                @foreach ($detail->fields as $field)
                                    <th class="{{ $kolomTotal->contains('id', $field->id) ? 'text-right' : '' }}">
                                        @if ($kolomTotal->contains('id', $field->id))
                                            <span data-total-for="{{ $field->field_name }}"
                                                  data-format="{{ $field->input_type }}">—</span>
                                        @elseif ($loop->first)
                                            Total
                                        @endif
                                    </th>
                                @endforeach
                                @if ($detail->allow_delete)<th></th>@endif
                            </tr>
                        </tfoot>
                    @endif
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
        segarkanTotal();
    });

    $(document).on('click', '.js-detail-remove', function () {
        const $body = $(this).closest('tbody');
        if ($body.children('tr').length > 1) {
            $(this).closest('tr').remove();
            segarkanTotal();
        }
    });


    // ------------------------------------------------------------------
    // Field terhitung
    //
    // Dihitung ulang di klien supaya angkanya bergerak saat diketik. Yang
    // tersimpan selalu dihitung ulang lagi di server dan nilai kiriman untuk
    // field ini diabaikan — jadi tidak ada yang bisa dipalsukan dari sini.
    // ------------------------------------------------------------------

    function namaField($input) {
        const nama = $input.attr('name') || '';
        const cocok = nama.match(/\[([^\[\]]+)\]$/);   // detail[items][0][qty]
        return cocok ? cocok[1] : nama;                  // atau nama polos di induk
    }

    function bulatkan(nilai, jenis) {
        return jenis === 'number' ? Math.round(nilai) : Math.round(nilai * 100) / 100;
    }

    function hitungRumus() {
        const sums = {};

        // 1. Baris detail lebih dulu — rumus induk menjumlahkan hasilnya.
        $('table[data-detail]').each(function () {
            const kode = $(this).data('detail');

            $(this).find('tbody tr').each(function () {
                const $baris = $(this);
                const nilai = {};

                $baris.find('[name]').each(function () {
                    nilai[namaField($(this))] = $(this).val();
                });

                // Urut kemunculan di DOM, sama dengan urutan field di server.
                $baris.find('[data-formula]').each(function () {
                    const $sel = $(this);
                    const hasil = bulatkan(
                        LcFormula.evaluate($sel.data('formula'), nilai, {}),
                        $sel.data('format')
                    );

                    $sel.val(hasil);
                    nilai[namaField($sel)] = hasil;
                });

                Object.keys(nilai).forEach(function (k) {
                    const n = parseFloat(nilai[k]);
                    const kunci = kode + '.' + k;
                    sums[kunci] = (sums[kunci] || 0) + (isNaN(n) ? 0 : n);
                });
            });
        });

        // 2. Baru field induk.
        const induk = {};

        $('[name]').not('[name^="detail["]').each(function () {
            induk[$(this).attr('name')] = $(this).val();
        });

        $('[data-formula]').not('table[data-detail] [data-formula]').each(function () {
            const $sel = $(this);
            const hasil = bulatkan(
                LcFormula.evaluate($sel.data('formula'), induk, sums),
                $sel.data('format')
            );

            $sel.val(hasil);
            induk[$sel.attr('name')] = hasil;
        });

        // Baris total menjumlahkan kolom yang bisa saja baru saja dihitung.
        if (typeof segarkanTotal === 'function') segarkanTotal();
    }

    if ($('[data-formula]').length) {
        $(document).on('input change', 'input, select, textarea', function () {
            // Field terhitung diisi oleh skrip ini sendiri; menanggapinya akan
            // memicu perhitungan berulang tanpa guna.
            if (!$(this).is('[data-formula]')) hitungRumus();
        });

        hitungRumus();
    }

    // ------------------------------------------------------------------
    // Baris total detail
    //
    // Dihitung di klien supaya angkanya berubah seketika saat diketik. Yang
    // tersimpan tetap nilai per baris — totalnya tampilan saja, tidak pernah
    // dikirim ke server, jadi tidak ada yang bisa dipalsukan lewat sini.
    // ------------------------------------------------------------------

    function formatTotal(nilai, jenis) {
        const desimal = (jenis === 'number') ? 0 : 2;

        const teks = nilai.toLocaleString('id-ID', {
            minimumFractionDigits: desimal,
            maximumFractionDigits: desimal,
        });

        if (jenis === 'currency') return 'Rp ' + teks;
        if (jenis === 'percentage') return teks + '%';
        return teks;
    }

    function hitungTotal($tabel) {
        $tabel.find('tfoot [data-total-for]').each(function () {
            const $sel = $(this);
            const nama = $sel.data('total-for');
            let jumlah = 0;

            // Dicari per baris, bukan sekali untuk seluruh tabel, agar baris
            // yang baru ditambahkan ikut terhitung tanpa perlu didaftarkan.
            $tabel.find('tbody tr').each(function () {
                const nilai = parseFloat($(this).find('[name$="[' + nama + ']"]').val());
                if (!isNaN(nilai)) jumlah += nilai;
            });

            $sel.text(formatTotal(jumlah, $sel.data('format')));
        });
    }

    function segarkanTotal() {
        $('table[data-detail]').each(function () { hitungTotal($(this)); });
    }

    $(document).on('input change', 'table[data-detail] tbody input', segarkanTotal);
    segarkanTotal();
});
</script>
@endpush
