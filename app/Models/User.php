<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable([
    'username', 'name', 'email', 'password', 'phone', 'avatar',
    'scope_value', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Cache per-request; sidebar memanggil can() puluhan kali per render. */
    private ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /** Seluruh kode permission milik user lewat role-nya. */
    public function permissionCodes(): Collection
    {
        return $this->permissionCache ??= Permission::query()
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('roles.is_active', true)
            ->distinct()
            ->pluck('permissions.code');
    }

    public function hasPermission(?string $code): bool
    {
        // Menu tanpa permission_code terbuka untuk semua user yang login.
        if ($code === null || $code === '') {
            return true;
        }

        return $this->permissionCodes()->contains($code);
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->where('is_active', true)->exists();
    }

    /** data_scope paling longgar dari seluruh role yang dimiliki. */
    public function dataScope(): string
    {
        $order = ['own' => 1, 'branch' => 2, 'custom' => 3, 'all' => 4];

        return $this->roles()
            ->where('is_active', true)
            ->pluck('data_scope')
            ->sortByDesc(fn ($s) => $order[$s] ?? 0)
            ->first() ?? 'own';
    }
}
