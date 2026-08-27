<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\FormFieldRequest;
use App\Models\Form;
use App\Models\FormDetail;
use App\Models\FormField;
use App\Services\DataSourceResolver;
use App\Services\Form\FormBuilderService;
use App\Services\Generator\TableInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormFieldController extends Controller
{
    public function __construct(
        private readonly FormBuilderService $builder,
        private readonly DataSourceResolver $sources,
        private readonly TableInspector $inspector,
    ) {}

    public function index(Request $request, Form $form): View
    {
        // ?detail=<id> memindahkan halaman ke field milik satu baris detail.
        $detail = $this->detailFrom($request, $form);

        return view('builder.fields.index', [
            'form' => $form,
            'detail' => $detail,
            'details' => $form->allDetails()->get(),
            'fields' => $this->fieldsFor($form, $detail),
            // Kolom tabel yang belum punya field — memudahkan menambah yang terlewat.
            'unmapped' => $this->unmappedColumns($form, $detail),
        ]);
    }

    public function create(Request $request, Form $form): View
    {
        $detail = $this->detailFrom($request, $form);

        return $this->form($form, new FormField([
            'form_detail_id' => $detail?->id,
            'input_type' => 'text',
            'width' => 6,
            'order_no' => (int) DB::table('form_fields')
                ->where('form_id', $form->id)
                ->where('form_detail_id', $detail?->id)
                ->max('order_no') + 1,
            'data_source_type' => 'none',
            'is_active' => true,
        ]), $detail);
    }

    public function store(FormFieldRequest $request, Form $form): RedirectResponse
    {
        $this->builder->snapshot($form, $request->user(), 'Sebelum tambah field');

        DB::transaction(function () use ($request, $form) {
            $id = DB::table('form_fields')->insertGetId([
                ...$this->values($request),
                'form_id' => $form->id,
                'form_detail_id' => $request->detail()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncOptions($request, $id);
        });

        $this->builder->flush($form);

        return redirect()->route('builder.fields.index', [$form, 'detail' => $request->input('form_detail_id')])
            ->with('success', 'Field berhasil ditambahkan.');
    }

    public function edit(Form $form, FormField $field): View
    {
        $this->assertOwned($form, $field);

        return $this->form($form, $field, $field->form_detail_id
            ? FormDetail::find($field->form_detail_id)
            : null);
    }

    public function update(FormFieldRequest $request, Form $form, FormField $field): RedirectResponse
    {
        $this->assertOwned($form, $field);
        $this->builder->snapshot($form, $request->user(), "Sebelum ubah field {$field->field_name}");

        DB::transaction(function () use ($request, $field) {
            DB::table('form_fields')->where('id', $field->id)->update([
                ...$this->values($request),
                'updated_at' => now(),
            ]);

            $this->syncOptions($request, $field->id);
        });

        $this->builder->flush($form);

        return redirect()->route('builder.fields.index', $form)
            ->with('success', 'Field berhasil diperbarui.');
    }

    public function destroy(Request $request, Form $form, FormField $field): RedirectResponse
    {
        $this->assertOwned($form, $field);
        $this->builder->snapshot($form, $request->user(), "Sebelum hapus field {$field->field_name}");

        DB::transaction(function () use ($field) {
            DB::table('form_field_options')->where('form_field_id', $field->id)->delete();
            DB::table('form_fields')->where('id', $field->id)->delete();
        });

        $this->builder->flush($form);

        return back()->with('success', 'Field berhasil dihapus. Kolom tabelnya tidak disentuh.');
    }

    /**
     * Kanvas tata letak: susun field secara visual dalam grid 12 kolom.
     *
     * Halaman ini tidak menyimpan apa pun sendiri — ia hanya cara lain
     * menyunting order_no dan width yang sudah ada di form_fields.
     */
    public function layout(Request $request, Form $form): View
    {
        $detail = $this->detailFrom($request, $form);

        return view('builder.fields.layout', [
            'form' => $form,
            'detail' => $detail,
            'details' => $form->allDetails()->get(),
            'fields' => $this->fieldsFor($form, $detail),
        ]);
    }

    /** Simpan urutan dan lebar sekaligus dari kanvas. */
    public function saveLayout(Request $request, Form $form): JsonResponse
    {
        $items = $request->input('items', []);

        $owned = DB::table('form_fields')
            ->where('form_id', $form->id)
            ->where('form_detail_id', $this->detailFrom($request, $form)?->id)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($items, $owned) {
            $order = 0;
            foreach ($items as $item) {
                if (! in_array((int) ($item['id'] ?? 0), $owned, true)) {
                    continue;
                }

                DB::table('form_fields')->where('id', $item['id'])->update([
                    'order_no' => ++$order,
                    // Lebar dibatasi 1–12 di sini juga; nilai dari klien
                    // tidak dipercaya walau UI-nya sudah membatasi.
                    'width' => max(1, min(12, (int) ($item['width'] ?? 6))),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->builder->flush($form);

        return response()->json(['ok' => true, 'saved' => count($items)]);
    }

    /** Simpan ulang urutan field hasil drag & drop. */
    public function reorder(Request $request, Form $form): JsonResponse
    {
        $ids = $request->input('order', []);

        // Hanya field milik form ini yang boleh diurutkan; id dari request
        // tidak dipercaya. Lingkupnya dipersempit ke induk atau satu detail
        // agar mengurutkan detail tidak menggeser urutan field induk.
        $owned = DB::table('form_fields')
            ->where('form_id', $form->id)
            ->where('form_detail_id', $this->detailFrom($request, $form)?->id)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($ids, $owned) {
            $order = 0;
            foreach ($ids as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DB::table('form_fields')->where('id', $id)->update(['order_no' => ++$order]);
                }
            }
        });

        $this->builder->flush($form);

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function form(Form $form, FormField $field, ?FormDetail $detail = null): View
    {
        // Field detail memakai kolom tabel detailnya, bukan tabel form induk.
        $table = $detail?->table_name ?? $form->table_name;

        return view('builder.fields.form', [
            'form' => $form,
            'detail' => $detail,
            'field' => $field,
            'columns' => $this->tableColumns($form, $detail),
            'tableName' => $table,
            'tables' => $this->inspector->availableTables(),
            'inputTypes' => FormFieldRequest::inputTypes(),
            'siblings' => $this->fieldsFor($form, $detail)
                ->where('id', '!=', $field->id ?? 0)->pluck('field_name'),
            'options' => $field->exists
                ? DB::table('form_field_options')->where('form_field_id', $field->id)
                    ->orderBy('order_no')->get()
                : collect(),
        ]);
    }

    /** Detail dari query string, dipastikan milik form ini. */
    private function detailFrom(Request $request, Form $form): ?FormDetail
    {
        $id = $request->input('detail') ?: $request->input('form_detail_id');

        if (! $id) {
            return null;
        }

        // id dari request tidak dipercaya: detail wajib milik form ini.
        return FormDetail::where('id', $id)->where('form_id', $form->id)->first();
    }

    private function fieldsFor(Form $form, ?FormDetail $detail)
    {
        return FormField::where('form_id', $form->id)
            ->where('form_detail_id', $detail?->id)
            ->orderBy('order_no')
            ->get();
    }

    private function values(FormFieldRequest $request): array
    {
        return [
            ...$request->safe()->except(['options', 'form_detail_id']),
            'is_required' => $request->boolean('is_required'),
            'is_unique' => $request->boolean('is_unique'),
            'show_total' => $request->boolean('show_total'),
            // Field terhitung selalu hanya-baca: nilainya berasal dari rumus,
            // dan apa pun yang diketik pengguna diabaikan saat disimpan.
            'is_readonly' => $request->filled('formula') || $request->boolean('is_readonly'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Opsi statis ditulis ulang seluruhnya setiap simpan. */
    private function syncOptions(FormFieldRequest $request, int $fieldId): void
    {
        DB::table('form_field_options')->where('form_field_id', $fieldId)->delete();

        if ($request->input('data_source_type') !== 'static') {
            return;
        }

        $order = 0;
        foreach ($request->input('options', []) as $option) {
            $value = $option['value'] ?? '';
            $label = $option['label'] ?? '';

            if ($value === '' && $label === '') {
                continue;
            }

            DB::table('form_field_options')->insert([
                'form_field_id' => $fieldId,
                'value' => $value,
                'label' => $label ?: $value,
                'order_no' => ++$order,
                'is_default' => ! empty($option['is_default']),
                'is_active' => true,
            ]);
        }
    }

    private function assertOwned(Form $form, FormField $field): void
    {
        abort_unless($field->form_id === $form->id, 404);
    }

    private function tableColumns(Form $form, ?FormDetail $detail = null): array
    {
        try {
            return $this->sources->allowedColumns($detail?->table_name ?? $form->table_name);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function unmappedColumns(Form $form, ?FormDetail $detail = null): array
    {
        $mapped = $this->fieldsFor($form, $detail)->pluck('field_name')->all();

        return array_values(array_diff(
            $this->tableColumns($form, $detail),
            $mapped,
            \App\Services\Generator\ColumnMapper::MANAGED,
            // Primary key dan kolom penghubung diisi engine, bukan pengguna.
            array_filter([$detail?->primary_key ?? $form->primary_key, $detail?->foreign_key]),
        ));
    }
}
