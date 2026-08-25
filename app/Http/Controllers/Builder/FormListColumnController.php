<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\FormListColumnRequest;
use App\Models\Form;
use App\Models\FormListColumn;
use App\Services\DataSourceResolver;
use App\Services\Form\FormBuilderService;
use App\Services\Generator\TableInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormListColumnController extends Controller
{
    public function __construct(
        private readonly FormBuilderService $builder,
        private readonly DataSourceResolver $sources,
        private readonly TableInspector $inspector,
    ) {}

    public function index(Form $form): View
    {
        return view('builder.columns.index', [
            'form' => $form,
            'columns' => $form->listColumns,
        ]);
    }

    public function create(Form $form): View
    {
        return $this->form($form, new FormListColumn([
            'source_type' => 'column',
            'format' => 'text',
            'align' => 'left',
            'is_visible' => true,
            'is_searchable' => true,
            'is_sortable' => true,
            'order_no' => (int) DB::table('form_list_columns')->where('form_id', $form->id)->max('order_no') + 1,
        ]));
    }

    public function store(FormListColumnRequest $request, Form $form): RedirectResponse
    {
        $this->builder->snapshot($form, $request->user(), 'Sebelum tambah kolom list');

        DB::table('form_list_columns')->insert([
            ...$this->values($request),
            'form_id' => $form->id,
        ]);

        $this->builder->flush($form);

        return redirect()->route('builder.columns.index', $form)
            ->with('success', 'Kolom list berhasil ditambahkan.');
    }

    public function edit(Form $form, FormListColumn $column): View
    {
        $this->assertOwned($form, $column);

        return $this->form($form, $column);
    }

    public function update(FormListColumnRequest $request, Form $form, FormListColumn $column): RedirectResponse
    {
        $this->assertOwned($form, $column);
        $this->builder->snapshot($form, $request->user(), "Sebelum ubah kolom list {$column->label}");

        DB::table('form_list_columns')->where('id', $column->id)->update($this->values($request));

        $this->builder->flush($form);

        return redirect()->route('builder.columns.index', $form)
            ->with('success', 'Kolom list berhasil diperbarui.');
    }

    public function destroy(Request $request, Form $form, FormListColumn $column): RedirectResponse
    {
        $this->assertOwned($form, $column);
        $this->builder->snapshot($form, $request->user(), "Sebelum hapus kolom list {$column->label}");

        DB::table('form_list_columns')->where('id', $column->id)->delete();
        $this->builder->flush($form);

        return back()->with('success', 'Kolom list berhasil dihapus.');
    }

    public function reorder(Request $request, Form $form): JsonResponse
    {
        $owned = DB::table('form_list_columns')->where('form_id', $form->id)->pluck('id')->all();

        DB::transaction(function () use ($request, $owned) {
            $order = 0;
            foreach ($request->input('order', []) as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DB::table('form_list_columns')->where('id', $id)->update(['order_no' => ++$order]);
                }
            }
        });

        $this->builder->flush($form);

        return response()->json(['ok' => true]);
    }

    /**
     * Isi ulang kolom list dari field form — jalan pintas ketika kolom list
     * terlanjur berantakan dan lebih cepat dimulai dari nol.
     */
    public function reset(Request $request, Form $form): RedirectResponse
    {
        $this->builder->snapshot($form, $request->user(), 'Sebelum reset kolom list');

        DB::transaction(function () use ($form) {
            DB::table('form_list_columns')->where('form_id', $form->id)->delete();

            $order = 0;
            foreach ($form->fields as $field) {
                if ($order >= 6 || in_array($field->input_type, ['textarea', 'editor', 'password', 'file'], true)) {
                    continue;
                }

                $isRelation = $field->data_source_type === 'table' && $field->label_field;

                DB::table('form_list_columns')->insert([
                    'form_id' => $form->id,
                    'label' => $field->label,
                    'source_type' => $isRelation ? 'relation' : 'column',
                    'column_name' => $field->field_name,
                    'relation_table' => $isRelation ? $field->data_source : null,
                    'relation_key' => $isRelation ? $field->value_field : null,
                    'relation_label' => $isRelation ? $field->label_field : null,
                    'format' => $isRelation ? 'text' : $this->formatFor($field->input_type),
                    'align' => in_array($field->input_type, ['number', 'decimal', 'currency', 'percentage'], true)
                        ? 'right' : 'left',
                    'is_visible' => true,
                    'is_searchable' => ! $isRelation && in_array($field->input_type, ['text', 'email', 'url'], true),
                    'is_sortable' => ! $isRelation,
                    'order_no' => ++$order,
                ]);
            }
        });

        $this->builder->flush($form);

        return back()->with('success', 'Kolom list disusun ulang dari field form.');
    }

    // ------------------------------------------------------------------

    private function form(Form $form, FormListColumn $column): View
    {
        return view('builder.columns.form', [
            'form' => $form,
            'column' => $column,
            'tableColumns' => $this->tableColumns($form),
            'tables' => $this->inspector->availableTables(),
            'canExpression' => auth()->user()->hasPermission('system.raw_query'),
        ]);
    }

    private function values(FormListColumnRequest $request): array
    {
        $data = $request->safe()->all();
        $type = $data['source_type'];

        // Kolom yang tidak relevan dengan jenis sumber dikosongkan, supaya
        // sisa isian lama tidak ikut terbawa dan membingungkan nanti.
        if ($type !== 'relation') {
            $data['relation_table'] = $data['relation_key'] = $data['relation_label'] = null;
        }
        if ($type !== 'expression') {
            $data['expression'] = null;
        }
        if ($type === 'expression') {
            $data['column_name'] = null;
        }

        return [
            ...$data,
            'is_visible' => $request->boolean('is_visible'),
            'is_searchable' => $request->boolean('is_searchable'),
            'is_sortable' => $request->boolean('is_sortable'),
        ];
    }

    private function formatFor(string $inputType): string
    {
        return match ($inputType) {
            'number' => 'number',
            'decimal' => 'decimal',
            'currency' => 'currency',
            'percentage' => 'percentage',
            'date' => 'date',
            'datetime' => 'datetime',
            'switch', 'checkbox' => 'boolean',
            'select', 'select2' => 'badge',
            'image' => 'image',
            default => 'text',
        };
    }

    private function assertOwned(Form $form, FormListColumn $column): void
    {
        abort_unless($column->form_id === $form->id, 404);
    }

    private function tableColumns(Form $form): array
    {
        try {
            return $this->sources->allowedColumns($form->table_name);
        } catch (\Throwable) {
            return [];
        }
    }
}
