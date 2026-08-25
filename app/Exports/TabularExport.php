<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar ekspor generik: judul kolom + baris, dipakai form maupun report.
 *
 * Nilai sudah diformat oleh pemanggil (mata uang, tanggal, dsb.) sehingga
 * hasil ekspor sama persis dengan yang terlihat di layar.
 */
class TabularExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly string $title = 'Data',
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Nama sheet Excel maksimal 31 karakter dan menolak sebagian tanda baca.
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '-', $this->title), 0, 31) ?: 'Data';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
