<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\FormSettingRequest;
use App\Models\Form;
use App\Services\DataSourceResolver;
use App\Services\Form\FormBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FormBuilderController extends Controller
{
    public function __construct(
        private readonly FormBuilderService $builder,
        private readonly DataSourceResolver $sources,
    ) {}

    public function index(): View
    {
        return view('builder.forms.index', [
            'forms' => Form::withCount('allFields')->orderBy('name')->get(),
        ]);
    }

    public function edit(Form $form): View
    {
        return view('builder.forms.edit', [
            'form' => $form,
            'columns' => $this->tableColumns($form),
            'versions' => DB::table('form_versions')
                ->where('form_id', $form->id)
                ->orderByDesc('version')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(FormSettingRequest $request, Form $form): RedirectResponse
    {
        // Snapshot diambil sebelum perubahan, sehingga versi terekam adalah
        // keadaan yang bisa dikembalikan.
        $this->builder->snapshot($form, $request->user(), $request->input('note'));

        $form->update([
            ...$request->safe()->except('note'),
            'use_soft_delete' => $request->boolean('use_soft_delete'),
            'use_audit_column' => $request->boolean('use_audit_column'),
            'allow_create' => $request->boolean('allow_create'),
            'allow_edit' => $request->boolean('allow_edit'),
            'allow_delete' => $request->boolean('allow_delete'),
            'allow_export' => $request->boolean('allow_export'),
            'allow_print' => $request->boolean('allow_print'),
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);

        $this->builder->flush($form);

        return back()->with('success', 'Pengaturan form berhasil disimpan.');
    }

    public function destroy(Request $request, Form $form): RedirectResponse
    {
        $code = $form->code;

        DB::transaction(function () use ($form) {
            $fieldIds = DB::table('form_fields')->where('form_id', $form->id)->pluck('id');
            DB::table('form_field_options')->whereIn('form_field_id', $fieldIds)->delete();
            DB::table('form_fields')->where('form_id', $form->id)->delete();
            DB::table('form_list_columns')->where('form_id', $form->id)->delete();
            DB::table('form_actions')->where('form_id', $form->id)->delete();
            DB::table('form_details')->where('form_id', $form->id)->delete();
            DB::table('form_versions')->where('form_id', $form->id)->delete();
            // Menu yang menunjuk form ini ikut dibuang agar sidebar tidak
            // menyisakan tautan mati.
            DB::table('menus')->where('link_type', 'form')->where('target_value', $form->code)->delete();
            $form->delete();
        });

        $this->builder->flush($form);
        app(\App\Services\MenuService::class)->flush();

        return redirect()->route('builder.forms.index')
            ->with('success', "Form '{$code}' berhasil dihapus. Tabel bisnisnya tidak disentuh.");
    }

    public function restore(Request $request, Form $form, int $version): RedirectResponse
    {
        $this->builder->restore($form, $version, $request->user());

        return back()->with('success', "Definisi form dikembalikan ke versi {$version}.");
    }

    /** Kolom tabel target, untuk dropdown di form pengaturan. */
    private function tableColumns(Form $form): array
    {
        try {
            return $this->sources->allowedColumns($form->table_name);
        } catch (\Throwable) {
            return [];
        }
    }
}
