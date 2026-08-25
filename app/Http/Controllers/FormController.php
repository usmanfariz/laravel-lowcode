<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleRecordException;
use App\Models\Form;
use App\Models\FormField;
use App\Services\DataSourceResolver;
use App\Services\ExportService;
use App\Services\Form\FormActionRenderer;
use App\Services\Form\FormQueryBuilder;
use App\Services\Form\FormRenderer;
use App\Services\Form\FormRepository;
use App\Services\Form\FormService;
use App\Services\Form\FormValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FormController extends Controller
{
    public function __construct(
        private readonly FormService $forms,
        private readonly FormRenderer $renderer,
        private readonly DataSourceResolver $sources,
        private readonly FormQueryBuilder $listQuery,
        private readonly FormRepository $repository,
        private readonly FormValidator $validator,
        private readonly ExportService $export,
        private readonly FormActionRenderer $actions,
    ) {}

    /** Ekspor halaman list form dengan pencarian yang sedang berlaku. */
    public function export(Request $request, string $code, string $format): Response
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'export');

        $allowed = $format === 'print' ? $form->allow_print : $form->allow_export;
        abort_unless($allowed, 403, "Form ini tidak mengizinkan format '{$format}'.");

        $columns = $this->listQuery->columns($form);

        // length besar dipakai untuk menarik seluruh baris; batas kerasnya
        // ditegakkan assertWithinLimit di bawah.
        $result = $this->listQuery->paginate($form, $request->user(), [
            'search' => $request->input('search'),
            'start' => 0,
            'length' => ExportService::HARD_LIMIT,
        ]);

        // Cetak selalu sinkron: hasilnya halaman, bukan berkas untuk diunduh.
        if ($format !== 'print' && ! $this->export->fitsSynchronously($result['filtered'], null)) {
            $job = $this->export->queue(
                'form', $form->code, $form->title ?: $form->name, $format,
                ['search' => $request->input('search')],
                $request->user(),
            );

            return redirect()->route('exports.index')->with('success',
                "Data terlalu banyak untuk diunduh langsung, jadi ekspor #{$job->id} "
                .'dikerjakan di latar belakang. Berkasnya muncul di halaman ini setelah selesai.');
        }

        $rows = [];
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach ($columns as $i => $column) {
                $value = $this->format($row->{'c'.$i} ?? null, $column);
                $line[] = is_bool($value) ? ($value ? 'Ya' : 'Tidak') : $value;
            }
            $rows[] = $line;
        }

        $title = $form->title ?: $form->name;
        $headings = $columns->pluck('label')->all();
        $totals = [];

        if ($format === 'print') {
            return response()->view('exports.print', compact('title', 'headings', 'rows', 'totals'));
        }

        return $this->export->download($format, $title, $headings, $rows, $title);
    }

    public function index(Request $request, string $code): View
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'view');

        $user = $request->user();

        return view('forms.index', [
            'form' => $form,
            'columns' => $this->listQuery->columns($form),
            'toolbarActions' => $this->actions->forPosition($form, 'toolbar', $user),
            'rowActions' => $this->actions->forPosition($form, 'row', $user),
            'bulkActions' => $this->actions->forPosition($form, 'bulk', $user),
        ]);
    }

    /** Endpoint server-side DataTables untuk halaman list. */
    public function data(Request $request, string $code): JsonResponse
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'view');

        $result = $this->listQuery->paginate($form, $request->user(), [
            'search' => $request->input('search.value'),
            'start' => $request->input('start', 0),
            'length' => $request->input('length', $form->per_page),
            'order_column' => $request->input('order.0.column'),
            'order_dir' => $request->input('order.0.dir'),
        ]);

        $conditionValues = $this->conditionValues($form, collect($result['rows'])->pluck('__id')->all());

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => collect($result['rows'])->map(function ($row) use ($result, $conditionValues) {
                $out = ['__id' => $row->__id];

                foreach ($result['columns'] as $i => $column) {
                    $out['c'.$i] = $this->format($row->{'c'.$i} ?? null, $column);
                }

                // Nilai mentah untuk mengevaluasi show_condition di klien.
                $out['__cond'] = $conditionValues[$row->__id] ?? new \stdClass;

                return $out;
            }),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'create');
        abort_unless($form->allow_create, 403, 'Form ini tidak mengizinkan penambahan data.');

        $data = $this->validated($request, $form, null);

        $id = $this->repository->create($form, $data, $request->user());

        return redirect()->to(url("forms/{$form->code}"))
            ->with('success', ($form->title ?: $form->name).' berhasil ditambahkan.');
    }

    public function update(Request $request, string $code, string $id): RedirectResponse
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'edit');
        abort_unless($form->allow_edit, 403, 'Form ini tidak mengizinkan perubahan data.');

        $data = $this->validated($request, $form, $id);

        try {
            $this->repository->update(
                $form, $id, $data, $request->user(),
                $request->input('__version')
            );
        } catch (StaleRecordException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(url("forms/{$form->code}"))
            ->with('success', ($form->title ?: $form->name).' berhasil diperbarui.');
    }

    public function destroy(Request $request, string $code, string $id): RedirectResponse
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'delete');
        abort_unless($form->allow_delete, 403, 'Form ini tidak mengizinkan penghapusan data.');

        $this->repository->delete($form, $id, $request->user());

        return redirect()->to(url("forms/{$form->code}"))
            ->with('success', ($form->title ?: $form->name).' berhasil dihapus.');
    }

    /**
     * Validasi request memakai aturan yang diturunkan dari metadata, lalu
     * gabungkan berkas unggahan ke hasilnya.
     */
    private function validated(Request $request, $form, mixed $id): array
    {
        $data = $request->validate(
            $this->validator->rules($form, $id),
            [],
            $this->validator->attributes($form)
        );

        foreach ($form->fields as $field) {
            if (! in_array($field->input_type, ['file', 'image'], true)) {
                continue;
            }

            if ($request->hasFile($field->field_name)) {
                $data[$field->field_name] = $request->file($field->field_name);
            }

            if ($request->filled($field->field_name.'_existing')) {
                $data[$field->field_name.'_existing'] = $request->input($field->field_name.'_existing');
            }
        }

        $data['detail'] = $request->input('detail', []);

        return $data;
    }

    /** Terapkan format tampilan kolom list. */
    private function format(mixed $value, $column): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($column->format) {
            'number' => number_format((float) $value, 0, ',', '.'),
            'decimal' => number_format((float) $value, 2, ',', '.'),
            'currency' => 'Rp '.number_format((float) $value, 2, ',', '.'),
            'percentage' => number_format((float) $value, 2, ',', '.').'%',
            'date' => optional(Carbon::parse($value))->format(setting('date_format', 'd/m/Y')),
            'datetime' => optional(Carbon::parse($value))->format(setting('date_format', 'd/m/Y').' H:i'),
            'boolean' => (bool) $value,
            default => e((string) $value),
        };
    }

    public function create(Request $request, string $code): View
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'create');

        abort_unless($form->allow_create, 403, 'Form ini tidak mengizinkan penambahan data.');

        return view('forms.form', [
            'form' => $form,
            'row' => [],
            'detailRows' => [],
            'id' => null,
            'renderer' => $this->renderer,
        ]);
    }

    public function edit(Request $request, string $code, string $id): View
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'edit');

        abort_unless($form->allow_edit, 403, 'Form ini tidak mengizinkan perubahan data.');

        return view('forms.form', [
            'form' => $form,
            'row' => $this->repository->find($form, $id, $request->user()),
            'detailRows' => $this->repository->detailRows($form, $id),
            'id' => $id,
            'renderer' => $this->renderer,
        ]);
    }

    /** Opsi untuk select bergantung dan ajax_select. */
    public function options(Request $request, string $code, int $fieldId): JsonResponse
    {
        $form = $this->forms->byCode($code);
        $this->authorizeAction($request, $form, 'view');

        // Field harus benar-benar milik form ini — id dari URL tidak dipercaya.
        $field = FormField::where('id', $fieldId)
            ->where('form_id', $form->id)
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless($field->data_source_type === 'table', 404);

        $filter = $field->data_filter ?? [];

        // Nilai induk menyaring opsi anak, mis. kota disaring oleh provinsi.
        if ($field->depends_on && $field->depends_column && $request->filled('parent')) {
            $filter[$field->depends_column] = $request->input('parent');
        }

        $options = $this->sources->options(
            $field->data_source,
            $field->value_field,
            $field->label_field,
            $filter,
            $field->data_order_by,
        );

        if ($search = $request->string('q')->toString()) {
            $options = $options->filter(
                fn ($o) => stripos($o['label'], $search) !== false
            )->values();
        }

        return response()->json(['results' => $options->map(fn ($o) => [
            'id' => $o['value'],
            'text' => $o['label'],
        ])->values()]);
    }

    /**
     * Nilai kolom yang dipakai show_condition, dikunci per id baris.
     *
     * Diambil terpisah alih-alih ikut di SELECT utama supaya tidak mengacaukan
     * indeks kolom c0..cN yang sudah dipakai DataTables.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function conditionValues(Form $form, array $ids): array
    {
        $columns = $this->actions->conditionColumns($form);

        if ($columns === [] || $ids === []) {
            return [];
        }

        $table = $form->table_name;
        $key = $this->sources->assertColumn($table, $form->primary_key);

        // Kolom kondisi berasal dari metadata, jadi tetap lewat whitelist.
        $safe = [];
        foreach ($columns as $column) {
            try {
                $safe[] = $this->sources->assertColumn($table, $column);
            } catch (\Throwable) {
                continue;
            }
        }

        if ($safe === []) {
            return [];
        }

        return $this->sources->query($table)
            ->select(array_unique([$key, ...$safe]))
            ->whereIn($key, $ids)
            ->get()
            ->keyBy($key)
            ->map(fn ($row) => collect((array) $row)->only($safe)->all())
            ->all();
    }

    private function authorizeAction(Request $request, Form $form, string $action): void
    {
        $permission = $form->permission($action);

        if ($permission && ! $request->user()->hasPermission($permission)) {
            abort(403, "Anda tidak memiliki izin '{$permission}'.");
        }
    }
}
