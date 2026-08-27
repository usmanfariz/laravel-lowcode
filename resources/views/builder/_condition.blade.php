{{--
    Editor kondisi baris, dipakai untuk lock_condition (form) maupun
    show_condition (aksi). Bentuk JSON-nya sama, jadi editornya juga satu.

    Variabel: $prefix, $columns, $condition, $judul, $bantuan
--}}
@php
    $kondisi = $condition ?: [];
    $berkunciBanyak = count($kondisi) > 1;

    $kolomNama = $prefix.'_column';
    $nilaiNama = $prefix.'_value';

    $kolomTerpilih = old($kolomNama, \App\Support\ConditionInput::column($kondisi));
    $nilaiTerpilih = old($nilaiNama, \App\Support\ConditionInput::value($kondisi));
@endphp

<div class="form-group">
    <label>{{ $judul }}</label>

    @if ($berkunciBanyak)
        <div class="alert alert-warning py-2 px-3 small">
            Kondisi ini memakai lebih dari satu kolom
            (<code>{{ implode(', ', array_keys($kondisi)) }}</code>), yang tidak bisa
            ditampilkan editor sederhana ini. Menyimpan form akan
            <strong>menggantinya</strong> dengan pasangan di bawah.
        </div>
    @endif

    <div class="row">
        <div class="col-5">
            <select name="{{ $kolomNama }}" class="form-control @error($kolomNama) is-invalid @enderror">
                <option value="">— tanpa kondisi —</option>
                @foreach ($columns as $kolom)
                    <option value="{{ $kolom }}" @selected($kolomTerpilih === $kolom)>{{ $kolom }}</option>
                @endforeach
            </select>
            @error($kolomNama)<span class="invalid-feedback">{{ $message }}</span>@enderror
        </div>
        <div class="col-7">
            <input type="text" name="{{ $nilaiNama }}"
                   class="form-control @error($nilaiNama) is-invalid @enderror"
                   value="{{ $nilaiTerpilih }}" placeholder="posted">
            @error($nilaiNama)<span class="invalid-feedback">{{ $message }}</span>@enderror
        </div>
    </div>

    <small class="form-text text-muted">
        {{ $bantuan }}
        Beberapa nilai dipisah koma berarti "salah satu": <code>posted, void</code>.
    </small>
</div>
