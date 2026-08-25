<?php

namespace App\Services\Report;

use App\Models\ReportFilter;
use App\Services\DataSourceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportFilterRenderer
{
    public function __construct(private readonly DataSourceResolver $sources) {}

    /** @return Collection<int, array{value: mixed, label: string}> */
    public function optionsFor(ReportFilter $filter): Collection
    {
        return match ($filter->data_source_type) {
            'static' => collect($filter->static_options ?? [])->map(fn ($label, $value) => [
                'value' => $value,
                'label' => (string) $label,
            ])->values(),

            'table' => $this->tableOptions($filter),

            default => collect(),
        };
    }

    private function tableOptions(ReportFilter $filter): Collection
    {
        if (! $filter->data_source || ! $filter->value_field || ! $filter->label_field) {
            return collect();
        }

        // Array biasa yang di-cache, bukan Collection — lihat docs/RANCANGAN.md §13.
        return collect(Cache::remember(
            'report.filter.options.'.$filter->id,
            now()->addMinutes(10),
            fn () => $this->sources->options(
                $filter->data_source,
                $filter->value_field,
                $filter->label_field,
                $filter->data_filter,
            )->all()
        ));
    }

    /**
     * Nilai filter yang sedang berlaku: dari request, kalau tidak ada dari
     * default metadata. Selalu larik agar operator apa pun tertangani sama.
     *
     * @return array<int, mixed>
     */
    public function valuesFor(ReportFilter $filter, array $input): array
    {
        $raw = $input[$filter->id] ?? $filter->default_values ?? [];

        return array_values(array_filter(
            is_array($raw) ? $raw : [$raw],
            fn ($v) => $v !== null && $v !== ''
        ));
    }
}
