<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DataSourceRequest;
use App\Models\DataSource;
use App\Services\DataSourceResolver;
use App\Services\Generator\TableInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DataSourceController extends Controller
{
    /** Kolom yang hampir selalu perlu diblokir, ditawarkan sebagai usulan. */
    private const SENSITIVE = [
        'password', 'remember_token', 'api_token', 'secret', 'token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public function __construct(
        private readonly TableInspector $inspector,
        private readonly DataSourceResolver $resolver,
    ) {}

    public function index(): View
    {
        $sources = DataSource::orderBy('table_name')->get();

        return view('admin.data-sources.index', [
            'sources' => $sources,
            'usage' => $this->usageMap(),
            // Tabel yang ada di database tapi belum didaftarkan — inilah yang
            // membuat halaman ini berguna sebagai titik mulai.
            'unregistered' => $this->inspector->physicalTables()
                ->reject(fn ($t) => $sources->contains('table_name', $t['name']))
                ->reject(fn ($t) => in_array($t['name'], $this->laravelTables(), true))
                ->values(),
        ]);
    }

    public function create(Request $request): View
    {
        $table = $request->string('table')->toString();

        return $this->form(new DataSource([
            'connection' => 'mysql',
            'table_name' => $table,
            'label' => $table ? ucwords(str_replace('_', ' ', $table)) : '',
            'primary_key' => 'id',
            'is_readable' => true,
            'is_writable' => false,
            'is_active' => true,
        ]));
    }

    public function store(DataSourceRequest $request): RedirectResponse
    {
        DataSource::create([
            ...$request->safe()->except('blocked_columns'),
            'connection' => 'mysql',
            'blocked_columns' => $request->input('blocked_columns') ?: null,
            'is_readable' => $request->boolean('is_readable'),
            'is_writable' => $request->boolean('is_writable'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('data-sources.index')
            ->with('success', 'Sumber data berhasil didaftarkan.');
    }

    public function edit(DataSource $dataSource): View
    {
        return $this->form($dataSource);
    }

    public function update(DataSourceRequest $request, DataSource $dataSource): RedirectResponse
    {
        $dataSource->update([
            ...$request->safe()->except(['blocked_columns', 'table_name']),
            'blocked_columns' => $request->input('blocked_columns') ?: null,
            'is_readable' => $request->boolean('is_readable'),
            'is_writable' => $request->boolean('is_writable'),
            'is_active' => $request->boolean('is_active'),
        ]);

        // Daftar kolom yang diizinkan di-cache; tanpa dibuang, blocked_columns
        // baru tidak berlaku sampai 30 menit berikutnya.
        $this->resolver->flushColumns($dataSource->table_name);

        return redirect()->route('data-sources.index')
            ->with('success', 'Sumber data berhasil diperbarui.');
    }

    public function destroy(DataSource $dataSource): RedirectResponse
    {
        $usage = $this->usageMap()[$dataSource->table_name] ?? [];

        // Mencabut sumber yang masih dipakai membuat form dan report-nya
        // gagal 403 tanpa petunjuk apa pun. Lebih baik ditolak di sini.
        if ($usage !== []) {
            return back()->with('error',
                "Tabel '{$dataSource->table_name}' masih dipakai: ".implode(', ', $usage)
                .'. Ubah atau hapus dulu yang memakainya.');
        }

        $table = $dataSource->table_name;
        $dataSource->delete();
        $this->resolver->flushColumns($table);

        return redirect()->route('data-sources.index')
            ->with('success', "Sumber data '{$table}' dicabut. Tabelnya sendiri tidak disentuh.");
    }

    // ------------------------------------------------------------------

    private function form(DataSource $dataSource): View
    {
        $table = $dataSource->table_name;
        $columns = $table ? $this->inspector->rawColumns($table) : [];

        return view('admin.data-sources.form', [
            'source' => $dataSource,
            'columns' => $columns,
            'tables' => $this->inspector->physicalTables(),
            'suggested' => array_values(array_intersect($columns, self::SENSITIVE)),
            'usage' => $table ? ($this->usageMap()[$table] ?? []) : [],
        ]);
    }

    /**
     * Peta nama tabel → daftar metadata yang memakainya.
     *
     * Menyapu seluruh tempat nama tabel bisa muncul di metadata, bukan hanya
     * yang jelas — satu saja terlewat, penghapusan akan merusak diam-diam.
     *
     * @return array<string, array<int, string>>
     */
    private function usageMap(): array
    {
        $usage = [];

        $add = function (?string $table, string $label) use (&$usage) {
            if ($table) {
                $usage[$table][] = $label;
                $usage[$table] = array_values(array_unique($usage[$table]));
            }
        };

        foreach (DB::table('forms')->get(['code', 'table_name']) as $form) {
            $add($form->table_name, "form {$form->code}");
        }

        foreach (DB::table('form_details')
            ->join('forms', 'forms.id', '=', 'form_details.form_id')
            ->get(['forms.code', 'form_details.table_name']) as $detail) {
            $add($detail->table_name, "detail form {$detail->code}");
        }

        foreach (DB::table('form_fields')
            ->join('forms', 'forms.id', '=', 'form_fields.form_id')
            ->where('form_fields.data_source_type', 'table')
            ->get(['forms.code', 'form_fields.data_source']) as $field) {
            $add($field->data_source, "field form {$field->code}");
        }

        foreach (DB::table('form_list_columns')
            ->join('forms', 'forms.id', '=', 'form_list_columns.form_id')
            ->where('form_list_columns.source_type', 'relation')
            ->get(['forms.code', 'form_list_columns.relation_table']) as $column) {
            $add($column->relation_table, "kolom list {$column->code}");
        }

        foreach (DB::table('reports')->get(['code', 'base_table']) as $report) {
            $add($report->base_table, "report {$report->code}");
        }

        foreach (DB::table('report_joins')
            ->join('reports', 'reports.id', '=', 'report_joins.report_id')
            ->get(['reports.code', 'report_joins.table_name']) as $join) {
            $add($join->table_name, "join report {$join->code}");
        }

        foreach (DB::table('report_filters')
            ->join('reports', 'reports.id', '=', 'report_filters.report_id')
            ->where('report_filters.data_source_type', 'table')
            ->get(['reports.code', 'report_filters.data_source']) as $filter) {
            $add($filter->data_source, "filter report {$filter->code}");
        }

        return $usage;
    }

    /** Tabel milik Laravel dan metadata engine — bukan kandidat sumber data. */
    private function laravelTables(): array
    {
        return [
            'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'sessions', 'password_reset_tokens',
            'forms', 'form_details', 'form_fields', 'form_field_options',
            'form_list_columns', 'form_actions', 'form_versions',
            'reports', 'report_joins', 'report_columns', 'report_filters',
            'report_versions', 'data_sources', 'activity_logs', 'settings',
            'role_permissions', 'user_roles',
        ];
    }
}
