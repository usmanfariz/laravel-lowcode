<?php

namespace App\Http\Requests\Builder;

use App\Services\DataSourceResolver;
use App\Services\Report\ReportQueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'join_type' => ['required', Rule::in(['inner', 'left', 'right'])],
            'table_name' => ['required', 'string', 'max:150'],
            'table_alias' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'first_column' => ['required', 'string', 'max:150'],
            'operator' => ['required', Rule::in(['=', '!=', '>', '>=', '<', '<='])],
            'second_column' => ['required', 'string', 'max:150'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $report = $this->route('report');
                $join = $this->route('join');

                try {
                    app(DataSourceResolver::class)->assertReadable($this->input('table_name'));
                } catch (\Throwable $e) {
                    $validator->errors()->add('table_name', $e->getMessage());

                    return;
                }

                // Alias harus unik; dua tabel beralias sama membuat referensi
                // kolom mendua dan query gagal dengan pesan yang membingungkan.
                $taken = $report->joins()
                    ->when($join, fn ($q) => $q->where('id', '!=', $join->id))
                    ->pluck('table_alias')
                    ->push($report->alias())
                    ->filter()
                    ->all();

                if (in_array($this->input('table_alias'), $taken, true)) {
                    $validator->errors()->add('table_alias', 'Alias sudah dipakai tabel lain di report ini.');

                    return;
                }

                $this->checkColumns($validator, $report, $join);
            },
        ];
    }

    /**
     * Kedua sisi kondisi join divalidasi terhadap alias yang akan berlaku
     * SETELAH join ini ada — kalau tidak, join pertama ke sebuah tabel selalu
     * ditolak karena aliasnya belum terdaftar.
     */
    private function checkColumns(Validator $validator, $report, $join): void
    {
        $report = clone $report;
        $joins = $report->joins->reject(fn ($j) => $join && $j->id === $join->id)->values();

        $pending = new \App\Models\ReportJoin([
            'table_name' => $this->input('table_name'),
            'table_alias' => $this->input('table_alias'),
        ]);

        $report->setRelation('joins', $joins->push($pending));

        $builder = app(ReportQueryBuilder::class);

        foreach (['first_column', 'second_column'] as $key) {
            try {
                $builder->qualify($report, $this->input($key));
            } catch (\Throwable $e) {
                $validator->errors()->add($key, $e->getMessage());
            }
        }
    }

    public function attributes(): array
    {
        return [
            'table_name' => 'tabel', 'table_alias' => 'alias',
            'first_column' => 'kolom kiri', 'second_column' => 'kolom kanan',
            'join_type' => 'jenis join', 'order_no' => 'urutan',
        ];
    }
}
