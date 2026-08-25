<?php

namespace App\Http\Requests\Builder;

use App\Models\Report;
use App\Services\DataSourceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DashboardWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $widget = $this->route('widget');

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('dashboard_widgets', 'code')->ignore($widget?->id)],
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['stat', 'chart', 'table', 'text'])],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['required', Rule::in([
                'primary', 'info', 'success', 'warning', 'danger', 'secondary', 'dark',
            ])],
            'width' => ['required', 'integer', 'min:1', 'max:12'],
            'link_url' => ['nullable', 'string', 'max:255'],

            'source_table' => ['nullable', 'string', 'max:150'],
            'source_column' => ['nullable', 'string', 'max:100'],
            'aggregate' => ['nullable', Rule::in(['count', 'sum', 'avg', 'min', 'max'])],
            'format' => ['nullable', Rule::in(['number', 'decimal', 'currency', 'percentage'])],

            'report_code' => ['nullable', 'string', 'max:100'],
            'row_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'content' => ['nullable', 'string', 'max:5000'],

            'permission_code' => ['nullable', 'string', 'max:150'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $v) => match ($this->input('type')) {
            'stat' => $this->checkStat($v),
            'chart', 'table' => $this->checkReport($v),
            'text' => $this->checkText($v),
            default => null,
        }];
    }

    private function checkStat(Validator $validator): void
    {
        if (! $this->filled('source_table')) {
            $validator->errors()->add('source_table', 'Wajib diisi untuk widget angka.');

            return;
        }

        try {
            $resolver = app(DataSourceResolver::class);
            $resolver->assertReadable($this->input('source_table'));

            // COUNT tidak butuh kolom; agregat lain wajib menyebutkannya.
            if ($this->input('aggregate') !== 'count') {
                if (! $this->filled('source_column')) {
                    $validator->errors()->add('source_column',
                        'Wajib diisi untuk agregat selain COUNT.');

                    return;
                }

                $resolver->assertColumn($this->input('source_table'), $this->input('source_column'));
            }
        } catch (\Throwable $e) {
            $validator->errors()->add('source_table', $e->getMessage());
        }
    }

    private function checkReport(Validator $validator): void
    {
        if (! $this->filled('report_code')) {
            $validator->errors()->add('report_code', 'Wajib diisi untuk widget grafik atau tabel.');

            return;
        }

        $report = Report::where('code', $this->input('report_code'))->first();

        if ($report === null) {
            $validator->errors()->add('report_code',
                "Report '{$this->input('report_code')}' tidak ditemukan.");

            return;
        }

        // Widget grafik menumpang penentu label dan deret milik report-nya,
        // jadi report yang belum bisa digambar juga tidak berguna di sini.
        if ($this->input('type') === 'chart') {
            $reason = app(\App\Services\Report\ReportChartBuilder::class)
                ->reasonUnavailable($report->fresh(['joins', 'columns', 'filters']));

            if ($reason) {
                $validator->errors()->add('report_code', $reason);
            }
        }
    }

    private function checkText(Validator $validator): void
    {
        if (! $this->filled('content')) {
            $validator->errors()->add('content', 'Wajib diisi untuk widget teks.');
        }
    }

    /** @return array<string, mixed>|null pasangan kolom => nilai */
    public function filterPairs(): ?array
    {
        $text = trim((string) $this->input('filter_text'));

        if ($text === '') {
            return null;
        }

        $pairs = [];

        // Satu baris satu kondisi, format "kolom=nilai".
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$column, $value] = explode('=', $line, 2);
            $pairs[trim($column)] = trim($value);
        }

        return $pairs ?: null;
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode', 'title' => 'judul', 'type' => 'jenis widget',
            'source_table' => 'tabel sumber', 'source_column' => 'kolom',
            'report_code' => 'kode report', 'content' => 'isi',
            'width' => 'lebar', 'order_no' => 'urutan',
        ];
    }
}
