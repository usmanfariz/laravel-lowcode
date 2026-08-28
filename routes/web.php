<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DataSourceController;
use App\Http\Controllers\Admin\GeneratorController;
use App\Http\Controllers\Admin\HelpArticleController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Builder\DashboardBuilderController;
use App\Http\Controllers\Builder\FormBuilderController;
use App\Http\Controllers\Builder\FormFieldController;
use App\Http\Controllers\Builder\FormListColumnController;
use App\Http\Controllers\Builder\FormPartController;
use App\Http\Controllers\Builder\ReportBuilderController;
use App\Http\Controllers\Builder\ReportPartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Demo\ProductActionController;
use App\Http\Controllers\ExportJobController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\HelpBotController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Pengguna
    Route::get('users/data', [UserController::class, 'data'])
        ->middleware('permission:user.view')->name('users.data');
    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:user.view')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('permission:user.create')->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:user.create')->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:user.edit')->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:user.edit')->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:user.delete')->name('users.destroy');

    // Role & izin
    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:role.view')->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware('permission:role.create')->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:role.create')->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('permission:role.edit')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:role.edit')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:role.delete')->name('roles.destroy');

    // CRUD dinamis. Route statis didaftarkan lebih dulu supaya "create" dan
    // "options" tidak tertangkap sebagai {id}.
    Route::get('forms/{code}', [FormController::class, 'index'])->name('forms.index');
    Route::get('forms/{code}/create', [FormController::class, 'create'])->name('forms.create');
    Route::get('forms/{code}/data', [FormController::class, 'data'])->name('forms.data');
    Route::get('forms/{code}/export/{format}', [FormController::class, 'export'])
        ->whereIn('format', ['xlsx', 'csv', 'pdf', 'print'])->name('forms.export');
    Route::get('forms/{code}/options/{field}', [FormController::class, 'options'])
        ->whereNumber('field')->name('forms.options');
    Route::post('forms/{code}/action/{action}', [FormController::class, 'action'])
        ->name('forms.action');
    Route::post('forms/{code}', [FormController::class, 'store'])->name('forms.store');
    Route::get('forms/{code}/{id}/edit', [FormController::class, 'edit'])->name('forms.edit');
    Route::match(['put', 'patch'], 'forms/{code}/{id}', [FormController::class, 'update'])->name('forms.update');
    Route::delete('forms/{code}/{id}', [FormController::class, 'destroy'])->name('forms.destroy');

    // Sumber data — gerbang keamanan engine
    Route::middleware('permission:system.data_source')->group(function () {
        Route::get('data-sources', [DataSourceController::class, 'index'])->name('data-sources.index');
        Route::get('data-sources/create', [DataSourceController::class, 'create'])->name('data-sources.create');
        Route::post('data-sources', [DataSourceController::class, 'store'])->name('data-sources.store');
        Route::get('data-sources/{dataSource}/edit', [DataSourceController::class, 'edit'])->name('data-sources.edit');
        Route::put('data-sources/{dataSource}', [DataSourceController::class, 'update'])->name('data-sources.update');
        Route::delete('data-sources/{dataSource}', [DataSourceController::class, 'destroy'])->name('data-sources.destroy');
    });

    // Berkas ekspor terantre — milik masing-masing pengguna, tanpa permission khusus
    Route::get('exports', [ExportJobController::class, 'index'])->name('exports.index');
    Route::get('exports/status', [ExportJobController::class, 'status'])->name('exports.status');
    Route::get('exports/{exportJob}/download', [ExportJobController::class, 'download'])
        ->whereNumber('exportJob')->name('exports.download');
    Route::post('exports/prune', [ExportJobController::class, 'prune'])->name('exports.prune');
    Route::post('exports/{exportJob}/retry', [ExportJobController::class, 'retry'])
        ->whereNumber('exportJob')->name('exports.retry');
    Route::delete('exports/{exportJob}', [ExportJobController::class, 'destroy'])
        ->whereNumber('exportJob')->name('exports.destroy');

    // CONTOH — endpoint untuk tombol aksi form demo `product`.
    // Engine sengaja tidak mengurus arti "setujui" atau "arsipkan"; itu urusan
    // aplikasi. Blok ini aman dihapus bersama migrasi dan seeder demo.
    Route::prefix('demo/products')->name('demo.products.')->group(function () {
        Route::post('approve', [ProductActionController::class, 'approve'])
            ->middleware('permission:product.approve')->name('approve');
        Route::post('archive', [ProductActionController::class, 'archive'])
            ->middleware('permission:product.edit')->name('archive');
        Route::get('labels', [ProductActionController::class, 'printLabel'])
            ->middleware('permission:product.print')->name('labels');
    });

    // Log aktivitas
    Route::middleware('permission:system.activity_log')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/data', [ActivityLogController::class, 'data'])->name('activity-logs.data');
        Route::post('activity-logs/prune', [ActivityLogController::class, 'prune'])->name('activity-logs.prune');
        Route::get('activity-logs/{log}', [ActivityLogController::class, 'show'])
            ->whereNumber('log')->name('activity-logs.show');
    });

    // Report dinamis
    Route::get('reports/{code}', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{code}/data', [ReportController::class, 'data'])->name('reports.data');
    Route::get('reports/{code}/chart', [ReportController::class, 'chart'])->name('reports.chart');
    Route::get('reports/{code}/export/{format}', [ReportController::class, 'export'])
        ->whereIn('format', ['xlsx', 'csv', 'pdf', 'print'])->name('reports.export');

    // Builder form: generator + editor metadata
    Route::middleware('permission:system.builder.form')->group(function () {
        Route::get('builder/generate', [GeneratorController::class, 'index'])->name('generator.index');
        Route::get('builder/generate/{table}', [GeneratorController::class, 'preview'])->name('generator.preview');
        Route::post('builder/generate', [GeneratorController::class, 'store'])->name('generator.store');

        Route::get('builder/forms', [FormBuilderController::class, 'index'])->name('builder.forms.index');
        Route::get('builder/forms/{form}', [FormBuilderController::class, 'edit'])->name('builder.forms.edit');
        Route::put('builder/forms/{form}', [FormBuilderController::class, 'update'])->name('builder.forms.update');
        Route::delete('builder/forms/{form}', [FormBuilderController::class, 'destroy'])->name('builder.forms.destroy');
        Route::post('builder/forms/{form}/restore/{version}', [FormBuilderController::class, 'restore'])
            ->whereNumber('version')->name('builder.forms.restore');

        // Route statis didaftarkan sebelum {field} agar "create" dan "reorder"
        // tidak tertangkap sebagai id field.
        Route::get('builder/forms/{form}/fields', [FormFieldController::class, 'index'])->name('builder.fields.index');
        Route::get('builder/forms/{form}/fields/create', [FormFieldController::class, 'create'])->name('builder.fields.create');
        Route::post('builder/forms/{form}/fields/reorder', [FormFieldController::class, 'reorder'])->name('builder.fields.reorder');
        Route::get('builder/forms/{form}/layout', [FormFieldController::class, 'layout'])->name('builder.fields.layout');
        Route::post('builder/forms/{form}/layout', [FormFieldController::class, 'saveLayout'])->name('builder.fields.layout.save');
        Route::post('builder/forms/{form}/fields', [FormFieldController::class, 'store'])->name('builder.fields.store');
        Route::get('builder/forms/{form}/fields/{field}/edit', [FormFieldController::class, 'edit'])->name('builder.fields.edit');
        Route::put('builder/forms/{form}/fields/{field}', [FormFieldController::class, 'update'])->name('builder.fields.update');
        Route::delete('builder/forms/{form}/fields/{field}', [FormFieldController::class, 'destroy'])->name('builder.fields.destroy');

        // Kolom halaman list
        Route::get('builder/forms/{form}/columns', [FormListColumnController::class, 'index'])->name('builder.columns.index');
        Route::get('builder/forms/{form}/columns/create', [FormListColumnController::class, 'create'])->name('builder.columns.create');
        Route::post('builder/forms/{form}/columns/reorder', [FormListColumnController::class, 'reorder'])->name('builder.columns.reorder');
        Route::post('builder/forms/{form}/columns/reset', [FormListColumnController::class, 'reset'])->name('builder.columns.reset');
        Route::post('builder/forms/{form}/columns', [FormListColumnController::class, 'store'])->name('builder.columns.store');
        Route::get('builder/forms/{form}/columns/{column}/edit', [FormListColumnController::class, 'edit'])->name('builder.columns.edit');
        Route::put('builder/forms/{form}/columns/{column}', [FormListColumnController::class, 'update'])->name('builder.columns.update');
        Route::delete('builder/forms/{form}/columns/{column}', [FormListColumnController::class, 'destroy'])->name('builder.columns.destroy');

        // Baris detail (master-detail) dan tombol aksi
        Route::post('builder/forms/{form}/reorder/{part}', [FormPartController::class, 'reorder'])
            ->whereIn('part', ['details', 'actions'])->name('builder.forms.reorder');

        Route::get('builder/forms/{form}/details', [FormPartController::class, 'details'])->name('builder.details.index');
        Route::post('builder/forms/{form}/details', [FormPartController::class, 'storeDetail'])->name('builder.details.store');
        Route::put('builder/forms/{form}/details/{detail}', [FormPartController::class, 'updateDetail'])->name('builder.details.update');
        Route::delete('builder/forms/{form}/details/{detail}', [FormPartController::class, 'destroyDetail'])->name('builder.details.destroy');

        Route::get('builder/forms/{form}/actions', [FormPartController::class, 'actions'])->name('builder.actions.index');
        Route::post('builder/forms/{form}/actions', [FormPartController::class, 'storeAction'])->name('builder.actions.store');
        Route::put('builder/forms/{form}/actions/{action}', [FormPartController::class, 'updateAction'])->name('builder.actions.update');
        Route::delete('builder/forms/{form}/actions/{action}', [FormPartController::class, 'destroyAction'])->name('builder.actions.destroy');
    });

    // Dashboard builder
    Route::middleware('permission:system.dashboard')->group(function () {
        Route::get('builder/dashboard', [DashboardBuilderController::class, 'index'])->name('builder.dashboard.index');
        Route::post('builder/dashboard', [DashboardBuilderController::class, 'store'])->name('builder.dashboard.store');
        Route::post('builder/dashboard/reorder', [DashboardBuilderController::class, 'reorder'])->name('builder.dashboard.reorder');
        Route::put('builder/dashboard/{widget}', [DashboardBuilderController::class, 'update'])->name('builder.dashboard.update');
        Route::delete('builder/dashboard/{widget}', [DashboardBuilderController::class, 'destroy'])->name('builder.dashboard.destroy');
    });

    // Report builder
    Route::middleware('permission:system.builder.report')->group(function () {
        Route::get('builder/reports', [ReportBuilderController::class, 'index'])->name('builder.reports.index');
        Route::get('builder/reports/create', [ReportBuilderController::class, 'create'])->name('builder.reports.create');
        Route::post('builder/reports', [ReportBuilderController::class, 'store'])->name('builder.reports.store');
        Route::get('builder/reports/{report}', [ReportBuilderController::class, 'edit'])->name('builder.reports.edit');
        Route::put('builder/reports/{report}', [ReportBuilderController::class, 'update'])->name('builder.reports.update');
        Route::delete('builder/reports/{report}', [ReportBuilderController::class, 'destroy'])->name('builder.reports.destroy');

        Route::post('builder/reports/{report}/restore/{version}', [ReportBuilderController::class, 'restore'])
            ->whereNumber('version')->name('builder.reports.restore');

        Route::post('builder/reports/{report}/reorder/{part}', [ReportPartController::class, 'reorder'])
            ->whereIn('part', ['columns', 'filters', 'joins'])->name('builder.reports.reorder');

        Route::get('builder/reports/{report}/joins', [ReportPartController::class, 'joins'])->name('builder.reports.joins.index');
        Route::post('builder/reports/{report}/joins', [ReportPartController::class, 'storeJoin'])->name('builder.reports.joins.store');
        Route::put('builder/reports/{report}/joins/{join}', [ReportPartController::class, 'updateJoin'])->name('builder.reports.joins.update');
        Route::delete('builder/reports/{report}/joins/{join}', [ReportPartController::class, 'destroyJoin'])->name('builder.reports.joins.destroy');

        Route::get('builder/reports/{report}/columns', [ReportPartController::class, 'columns'])->name('builder.reports.columns.index');
        Route::post('builder/reports/{report}/columns', [ReportPartController::class, 'storeColumn'])->name('builder.reports.columns.store');
        Route::put('builder/reports/{report}/columns/{column}', [ReportPartController::class, 'updateColumn'])->name('builder.reports.columns.update');
        Route::delete('builder/reports/{report}/columns/{column}', [ReportPartController::class, 'destroyColumn'])->name('builder.reports.columns.destroy');

        Route::get('builder/reports/{report}/filters', [ReportPartController::class, 'filters'])->name('builder.reports.filters.index');
        Route::post('builder/reports/{report}/filters', [ReportPartController::class, 'storeFilter'])->name('builder.reports.filters.store');
        Route::put('builder/reports/{report}/filters/{filter}', [ReportPartController::class, 'updateFilter'])->name('builder.reports.filters.update');
        Route::delete('builder/reports/{report}/filters/{filter}', [ReportPartController::class, 'destroyFilter'])->name('builder.reports.filters.destroy');
    });

    // Pengaturan aplikasi
    Route::middleware('permission:system.setting')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // Chatbot bantuan — terbuka untuk semua yang login. Pembatasan laju ada
    // karena kotak ketik yang terpasang di setiap halaman adalah endpoint
    // paling mudah dipanggil berulang-ulang di aplikasi ini.
    Route::get('help/topics', [HelpBotController::class, 'topics'])->name('help.topics');
    Route::post('help/ask', [HelpBotController::class, 'ask'])
        ->middleware('throttle:30,1')->name('help.ask');
    Route::get('help/articles/{id}', [HelpBotController::class, 'article'])
        ->whereNumber('id')->name('help.article');

    // Basis pengetahuan chatbot
    Route::middleware('permission:system.help')->group(function () {
        Route::get('help-articles', [HelpArticleController::class, 'index'])->name('help-articles.index');
        Route::get('help-articles/create', [HelpArticleController::class, 'create'])->name('help-articles.create');
        Route::post('help-articles', [HelpArticleController::class, 'store'])->name('help-articles.store');
        Route::post('help-articles/prune', [HelpArticleController::class, 'prune'])->name('help-articles.prune');
        Route::get('help-articles/{helpArticle}/edit', [HelpArticleController::class, 'edit'])->name('help-articles.edit');
        Route::put('help-articles/{helpArticle}', [HelpArticleController::class, 'update'])->name('help-articles.update');
        Route::delete('help-articles/{helpArticle}', [HelpArticleController::class, 'destroy'])->name('help-articles.destroy');
    });

    // Menu
    Route::middleware('permission:system.menu')->group(function () {
        Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('menus/create', [MenuController::class, 'create'])->name('menus.create');
        Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
        Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });
});
