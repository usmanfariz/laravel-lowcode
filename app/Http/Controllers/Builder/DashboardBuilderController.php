<?php

namespace App\Http\Controllers\Builder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Builder\DashboardWidgetRequest;
use App\Models\DashboardWidget;
use App\Models\Permission;
use App\Models\Report;
use App\Services\Generator\TableInspector;
use App\Support\DropsNullDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardBuilderController extends Controller
{
    use DropsNullDefaults;

    public function __construct(private readonly TableInspector $inspector) {}

    public function index(): View
    {
        return view('builder.dashboard.index', [
            'widgets' => DashboardWidget::orderBy('order_no')->get(),
            ...$this->options(),
        ]);
    }

    public function store(DashboardWidgetRequest $request): RedirectResponse
    {
        DashboardWidget::create($this->values($request));

        return back()->with('success', 'Widget berhasil ditambahkan.');
    }

    public function update(DashboardWidgetRequest $request, DashboardWidget $widget): RedirectResponse
    {
        $widget->update($this->values($request));

        return redirect()->route('builder.dashboard.index')
            ->with('success', 'Widget berhasil diperbarui.');
    }

    public function destroy(DashboardWidget $widget): RedirectResponse
    {
        $code = $widget->code;
        $widget->delete();

        return back()->with('success', "Widget '{$code}' dihapus.");
    }

    public function reorder(Request $request): JsonResponse
    {
        $owned = DashboardWidget::pluck('id')->all();

        DB::transaction(function () use ($request, $owned) {
            $order = 0;
            foreach ($request->input('order', []) as $id) {
                if (in_array((int) $id, $owned, true)) {
                    DashboardWidget::where('id', $id)->update(['order_no' => ++$order]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------

    private function values(DashboardWidgetRequest $request): array
    {
        $data = $request->safe()->except('filter_text');
        $type = $data['type'];

        // Kolom yang tidak relevan dengan jenis widget dikosongkan, supaya
        // sisa isian lama tidak ikut terbawa dan membingungkan nanti.
        if ($type !== 'stat') {
            $data['source_table'] = $data['source_column'] = null;
            $data['filter'] = null;
        } else {
            $data['filter'] = $request->filterPairs();
        }

        if (! in_array($type, ['chart', 'table'], true)) {
            $data['report_code'] = null;
        }

        if ($type !== 'text') {
            $data['content'] = null;
        }

        return $this->dropNullDefaults(
            [...$data, 'is_active' => $request->boolean('is_active')],
            ['aggregate', 'format', 'row_limit', 'color', 'width']
        );
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'tables' => $this->inspector->availableTables(),
            'reports' => Report::where('is_active', true)->orderBy('name')->get(['code', 'name', 'type']),
            'permissions' => Permission::orderBy('code')->pluck('code'),
        ];
    }
}
