<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Pemakaian: ->middleware('permission:user.view')
     * Beberapa kode dipisah koma dan bersifat OR: 'permission:user.view,user.edit'
     */
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        foreach ($codes as $group) {
            foreach (explode(',', $group) as $code) {
                if ($user->hasPermission(trim($code))) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}
