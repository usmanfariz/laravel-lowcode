<?php

namespace App\Providers;

use App\Models\User;
use App\Services\MenuService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Menjembatani @can('user.edit') ke tabel permission.
        // null (bukan false) supaya policy lain tetap berkesempatan memutuskan.
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ?: null);

        // Sidebar butuh pohon menu di setiap halaman yang memakai layout.
        View::composer('layouts.adminlte.partials.sidebar', function ($view) {
            $view->with(
                'menuTree',
                auth()->check()
                    ? app(MenuService::class)->treeFor(auth()->user())
                    : collect()
            );
        });
    }
}
