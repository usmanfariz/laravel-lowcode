<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\Menu;
use App\Models\Permission;
use App\Services\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(): View
    {
        return view('admin.menus.index', [
            'tree' => $this->fullTree(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menus.form', [
            'menu' => new Menu(['is_active' => true, 'link_type' => 'route', 'order_no' => 0]),
            'parents' => $this->parentOptions(),
            'permissions' => Permission::orderBy('code')->pluck('code'),
        ]);
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        Menu::create([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active'),
            'open_new_tab' => $request->boolean('open_new_tab'),
        ]);

        $this->menus->flush();

        return redirect()->route('menus.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(Menu $menu): View
    {
        return view('admin.menus.form', [
            'menu' => $menu,
            'parents' => $this->parentOptions($menu->id),
            'permissions' => Permission::orderBy('code')->pluck('code'),
        ]);
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active'),
            'open_new_tab' => $request->boolean('open_new_tab'),
        ]);

        $this->menus->flush();

        return redirect()->route('menus.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        if ($menu->children()->exists()) {
            return back()->with('error', 'Menu masih punya anak. Hapus atau pindahkan anaknya dulu.');
        }

        $menu->delete();
        $this->menus->flush();

        return redirect()->route('menus.index')->with('success', 'Menu berhasil dihapus.');
    }

    /** Seluruh menu termasuk yang nonaktif — halaman kelola harus melihat semuanya. */
    private function fullTree(?int $parentId = null, array $all = null)
    {
        $all ??= Menu::orderBy('order_no')->get()->all();

        return collect($all)
            ->filter(fn (Menu $m) => $m->parent_id === $parentId)
            ->values()
            ->map(function (Menu $m) use ($all) {
                $m->setRelation('children', $this->fullTree($m->id, $all));

                return $m;
            });
    }

    /**
     * Pilihan induk, tanpa menu itu sendiri dan turunannya — mencegah siklus
     * sejak di form, sebelum validasi perlu menolaknya.
     */
    private function parentOptions(?int $excludeId = null)
    {
        $all = Menu::orderBy('order_no')->get();

        if ($excludeId === null) {
            return $all;
        }

        $blocked = [$excludeId];
        do {
            $before = count($blocked);
            foreach ($all as $m) {
                if (in_array($m->parent_id, $blocked, true) && ! in_array($m->id, $blocked, true)) {
                    $blocked[] = $m->id;
                }
            }
        } while (count($blocked) > $before);

        return $all->whereNotIn('id', $blocked)->values();
    }
}
