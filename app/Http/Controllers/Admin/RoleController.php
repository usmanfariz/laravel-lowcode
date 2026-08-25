<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role(['is_active' => true, 'data_scope' => 'own']),
            'groups' => $this->permissionGroups(),
            'selected' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $role = Role::create([
                ...$request->safe()->except('permissions'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $role->permissions()->sync($request->input('permissions', []));
        });

        return redirect()->route('roles.index')->with('success', 'Role berhasil ditambahkan.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'groups' => $this->permissionGroups(),
            'selected' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update([
                ...$request->safe()->except(['permissions', 'code']),
                'is_active' => $request->boolean('is_active'),
            ]);

            $role->permissions()->sync($request->input('permissions', []));
        });

        return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Role sistem dipakai seeder dan kode program; menghapusnya membuat
        // superadmin kehilangan seluruh akses tanpa cara memulihkan lewat UI.
        if ($role->is_system) {
            return back()->with('error', 'Role sistem tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Role masih dipakai pengguna. Lepaskan dulu dari penggunanya.');
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        return redirect()->route('roles.index')->with('success', 'Role berhasil dihapus.');
    }

    /** Permission dikelompokkan per group_name agar form centang terbaca. */
    private function permissionGroups()
    {
        return Permission::orderBy('group_name')->orderBy('code')->get()->groupBy('group_name');
    }
}
