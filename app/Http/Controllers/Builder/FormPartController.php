<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\FormActionRequest;
use App\Http\Requests\Builder\FormDetailRequest;
use App\Models\Form;
use App\Services\Form\LowcodeRegistry;
use App\Support\ConditionInput;
use App\Models\FormAction;
use App\Models\FormDetail;
use App\Services\Form\FormBuilderService;
use App\Services\Generator\TableInspector;
use App\Support\DropsNullDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pengelola baris detail (master-detail) dan tombol aksi form.
 *
 * Keduanya berbagi pola yang sama dengan bagian report, jadi digabung
 * seperti ReportPartController.
 */
class FormPartController extends Controller
{
    use DropsNullDefaults;

    public function __construct(
        private readonly FormBuilderService $builder,
        private readonly TableInspector $inspector,
    ) {}

    // ---------------- DETAIL ----------------

    public function details(Form $form): View
    {
        return view('builder.details.index', [
            'form' => $form,
            'details' => $form->allDetails()->get(),
            'tables' => $this->inspector->availableTables()->where('is_writable', true)->values(),
        ]);
    }

    public function storeDetail(FormDetailRequest $request, Form $form): RedirectResponse
    {
        $this->builder->snapshot($form, $request->user(), 'Sebelum tambah detail');

        DB::table('form_details')->insert([
            ...$this->detailValues($request),
            'form_id' => $form->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Form yang punya detail otomatis bertipe master_detail; tanpa ini
        // renderer tidak akan menggambar tabel detailnya.
        if ($form->type === 'single') {
            $form->update(['type' => 'master_detail']);
        }

        $this->builder->flush($form);

        return back()->with('success', 'Detail berhasil ditambahkan.');
    }

    public function updateDetail(FormDetailRequest $request, Form $form, FormDetail $detail): RedirectResponse
    {
        $this->assertOwned($form, $detail->form_id);
        $this->builder->snapshot($form, $request->user(), "Sebelum ubah detail {$detail->code}");

        DB::table('form_details')->where('id', $detail->id)->update([
            ...$this->detailValues($request),
            'updated_at' => now(),
        ]);

        $this->builder->flush($form);

        return redirect()->route('builder.details.index', $form)
            ->with('success', 'Detail berhasil diperbarui.');
    }

    public function destroyDetail(Request $request, Form $form, FormDetail $detail): RedirectResponse
    {
        $this->assertOwned($form, $detail->form_id);

        $fields = DB::table('form_fields')->where('form_detail_id', $detail->id)->count();

        if ($fields > 0) {
            return back()->with('error',
                "Detail '{$detail->code}' masih punya {$fields} field. Hapus field-nya dulu.");
        }

        $this->builder->snapshot($form, $request->user(), "Sebelum hapus detail {$detail->code}");
        DB::table('form_details')->where('id', $detail->id)->delete();

        if ($form->allDetails()->count() === 0 && $form->type === 'master_detail') {
            $form->update(['type' => 'single']);
        }

        $this->builder->flush($form);

        return back()->with('success', 'Detail berhasil dihapus.');
    }

    // ---------------- AKSI ----------------

    public function actions(Form $form): View
    {
        return view('builder.actions.index', [
            'form' => $form,
            'actions' => $form->allActions()->get(),
            // Hanya kuncinya; nama class tidak perlu sampai ke layar.
            'handlers' => array_keys(app(LowcodeRegistry::class)->handlers()),
            'handlersRusak' => app(LowcodeRegistry::class)->invalidHandlers(),
            'columns' => $this->formColumns($form),
        ]);
    }

    public function storeAction(FormActionRequest $request, Form $form): RedirectResponse
    {
        $this->builder->snapshot($form, $request->user(), 'Sebelum tambah aksi');

        DB::table('form_actions')->insert([
            ...$this->actionValues($request),
            'form_id' => $form->id,
        ]);

        $this->builder->flush($form);

        return back()->with('success', 'Aksi berhasil ditambahkan.');
    }

    public function updateAction(FormActionRequest $request, Form $form, FormAction $action): RedirectResponse
    {
        $this->assertOwned($form, $action->form_id);
        $this->builder->snapshot($form, $request->user(), "Sebelum ubah aksi {$action->code}");

        DB::table('form_actions')->where('id', $action->id)->update($this->actionValues($request));
        $this->builder->flush($form);

        return redirect()->route('builder.actions.index', $form)
            ->with('success', 'Aksi berhasil diperbarui.');
    }

    public function destroyAction(Request $request, Form $form, FormAction $action): RedirectResponse
    {
        $this->assertOwned($form, $action->form_id);
        $this->builder->snapshot($form, $request->user(), "Sebelum hapus aksi {$action->code}");

        DB::table('form_actions')->where('id', $action->id)->delete();
        $this->builder->flush($form);

        return back()->with('success', 'Aksi berhasil dihapus.');
    }

    public function reorder(Request $request, Form $form, string $part): JsonResponse
    {
        $table = match ($part) {
            'details' => 'form_details',
            'actions' => 'form_actions',
            default => abort(404),
        };

        $owned = DB::table($table)->where('form_id', $form->id)->pluck('id')->all();

        DB::transaction(function () use ($request, $table, $owned) {
            $order = 0;
            foreach ($request->input('order', []) as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DB::table($table)->where('id', $id)->update(['order_no' => ++$order]);
                }
            }
        });

        $this->builder->flush($form);

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function detailValues(FormDetailRequest $request): array
    {
        return $this->dropNullDefaults([
            ...$request->safe()->all(),
            'allow_add' => $request->boolean('allow_add'),
            'allow_delete' => $request->boolean('allow_delete'),
            'show_total_row' => $request->boolean('show_total_row'),
            'is_active' => $request->boolean('is_active'),
        ], ['min_rows']);
    }

    private function actionValues(FormActionRequest $request): array
    {
        $kondisi = ConditionInput::build(
            $request->input('condition_column'),
            $request->input('condition_value'),
        );

        return $this->dropNullDefaults([
            ...$request->safe()->except(['condition_column', 'condition_value']),
            'is_active' => $request->boolean('is_active'),
            // Ditulis lewat query builder, jadi tidak lewat cast model.
            // json_encode(null) menghasilkan string "null", bukan NULL.
            'show_condition' => $kondisi === null ? null : json_encode($kondisi),
        ], ['css_class']);
    }

    private function assertOwned(Form $form, int $formId): void
    {
        abort_unless($formId === $form->id, 404);
    }

    /**
     * Kolom yang boleh dipakai kondisi tampil. Sumber data bermasalah tidak
     * boleh mematikan halaman builder-nya.
     *
     * @return array<int, string>
     */
    private function formColumns(Form $form): array
    {
        try {
            return app(\App\Services\DataSourceResolver::class)->allowedColumns($form->table_name);
        } catch (\Throwable) {
            return [];
        }
    }
}
