<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuService
{
    private const CACHE_KEY = 'menu.tree';

    /**
     * Pohon menu aktif yang boleh dilihat user.
     *
     * Pohon mentah di-cache tanpa memandang user, lalu penyaringan permission
     * dilakukan per-request. Kalau cache dibuat per-user, satu user baru
     * langsung berarti satu entri cache baru dan cache jadi tidak ada gunanya.
     */
    public function treeFor(User $user): Collection
    {
        return $this->filter($this->rawTree(), $user);
    }

    private function rawTree(): Collection
    {
        // Yang di-cache sengaja array mentah, bukan model Eloquent: model
        // ter-serialize gagal di-unserialize begitu dibaca dari proses lain
        // (queue worker, CLI) dan berubah jadi __PHP_Incomplete_Class.
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('menus')
            ->where('is_active', true)
            ->orderBy('order_no')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all());

        return $this->buildTree(Menu::hydrate($rows), null);
    }

    private function buildTree(Collection $all, ?int $parentId): Collection
    {
        return $all
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Menu $menu) use ($all) {
                $menu->setRelation('children', $this->buildTree($all, $menu->id));

                return $menu;
            });
    }

    private function filter(Collection $items, User $user): Collection
    {
        return $items
            ->map(function (Menu $menu) use ($user) {
                $menu->setRelation('children', $this->filter($menu->children, $user));

                return $menu;
            })
            ->filter(function (Menu $menu) use ($user) {
                // Header tanpa anak yang lolos permission tidak perlu tampil,
                // supaya sidebar tidak menyisakan judul kosong.
                if ($menu->link_type === 'header') {
                    return $menu->children->isNotEmpty();
                }

                return $user->hasPermission($menu->permission_code);
            })
            ->values();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
