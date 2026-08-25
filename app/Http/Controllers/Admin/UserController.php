<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index');
    }

    /** Endpoint server-side DataTables. */
    public function data(Request $request): JsonResponse
    {
        $query = User::query()->with('roles:id,name');

        $total = $query->clone()->count();

        if ($search = $request->input('search.value')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $filtered = $query->clone()->count();

        // Kolom sortir di-whitelist; nilai dari request tidak boleh langsung
        // masuk orderBy karena itu jalur injeksi.
        $sortable = ['username', 'name', 'email', 'is_active'];
        $orderCol = $sortable[$request->input('order.0.column')] ?? 'username';
        $orderDir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        $rows = $query->orderBy($orderCol, $orderDir)
            ->skip((int) $request->input('start', 0))
            ->take(min((int) $request->input('length', 25), 100))
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows->map(fn (User $u) => [
                'id' => $u->id,
                'username' => e($u->username),
                'name' => e($u->name),
                'email' => e($u->email),
                'roles' => $u->roles->map(fn ($r) => e($r->name))->implode(', '),
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'selectedRoles' => [],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->except(['roles', 'password']),
                'password' => $request->string('password')->toString(),
                'is_active' => $request->boolean('is_active'),
            ]);

            $user->roles()->sync($request->input('roles', []));
        });

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'selectedRoles' => $user->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $data = $request->safe()->except(['roles', 'password']);
            $data['is_active'] = $request->boolean('is_active');

            // Password kosong berarti "jangan diubah", bukan "kosongkan".
            if ($request->filled('password')) {
                $data['password'] = $request->string('password')->toString();
            }

            $user->update($data);
            $user->roles()->sync($request->input('roles', []));
        });

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}
