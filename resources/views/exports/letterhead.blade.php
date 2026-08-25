{{-- Kop perusahaan untuk halaman cetak dan PDF.

     DomPDF membaca gambar lewat sistem berkas, peramban lewat URL — karena itu
     pemanggil menyatakan dirinya dengan $pdf. Tata letaknya memakai tabel, bukan
     flexbox, karena DomPDF hanya mendukung sebagian CSS. --}}
@php
    $pdf = $pdf ?? false;
    $logo = $pdf
        ? (setting_file_path('company_logo') ?: setting_file_path('app_logo'))
        : (setting_file('company_logo') ?: setting_file('app_logo'));
    $nama = setting('company_name') ?: setting('app_name', config('app.name'));
    $kontak = array_filter([
        setting('company_phone'),
        setting('company_email'),
        setting('company_website'),
    ]);
@endphp

@if (setting('print_show_header', true))
    <table style="width: 100%; border: none; border-bottom: 2px solid #333; margin-bottom: 10px">
        <tr>
            @if ($logo)
                <td style="border: none; width: 70px; padding: 0 8px 6px 0">
                    <img src="{{ $logo }}" alt="" style="max-height: 56px; max-width: 60px">
                </td>
            @endif
            <td style="border: none; padding: 0 0 6px">
                <div style="font-size: 14px; font-weight: bold">{{ $nama }}</div>
                @if ($alamat = setting('company_address'))
                    <div style="font-size: 10px; color: #555">{{ $alamat }}</div>
                @endif
                @if ($kontak)
                    <div style="font-size: 10px; color: #555">{{ implode(' &middot; ', $kontak) }}</div>
                @endif
                @if ($npwp = setting('company_tax_id'))
                    <div style="font-size: 10px; color: #555">NPWP {{ $npwp }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif
