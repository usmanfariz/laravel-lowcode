<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateFormRequest;
use App\Services\DataSourceResolver;
use App\Services\Generator\FormGenerator;
use App\Services\Generator\TableInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneratorController extends Controller
{
    public function __construct(
        private readonly TableInspector $inspector,
        private readonly FormGenerator $generator,
        private readonly DataSourceResolver $sources,
    ) {}

    /** Pilih tabel sumber. */
    public function index(): View
    {
        return view('admin.generator.index', [
            'tables' => $this->inspector->availableTables(),
            'existing' => \App\Models\Form::pluck('code', 'table_name'),
        ]);
    }

    /** Pratinjau field hasil pemetaan, sebelum apa pun ditulis. */
    public function preview(Request $request, string $table): View
    {
        // Tabel dari URL tidak dipercaya; whitelist diperiksa lebih dulu.
        $this->sources->assertReadable($table);

        return view('admin.generator.preview', [
            'table' => $table,
            'fields' => $this->generator->preview($table),
            'columns' => $this->inspector->columns($table),
            'suggest' => $this->generator->suggest($table),
        ]);
    }

    public function store(GenerateFormRequest $request): RedirectResponse
    {
        try {
            $form = $this->generator->generate(
                $request->string('table')->toString(),
                [
                    'code' => $request->string('code')->toString(),
                    'name' => $request->string('name')->toString(),
                    'title' => $request->string('title')->toString(),
                    'description' => $request->input('description'),
                    'permission_prefix' => $request->string('permission_prefix')->toString(),
                    'scope_column' => $request->input('scope_column'),
                    'create_menu' => $request->boolean('create_menu'),
                ],
                $request->user(),
                $request->input('columns', []),
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(url("forms/{$form->code}"))
            ->with('success', "Form '{$form->name}' berhasil dibuat dari tabel {$form->table_name}.");
    }
}
