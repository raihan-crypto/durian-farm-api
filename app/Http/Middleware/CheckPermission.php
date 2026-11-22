<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Akun Anda dinonaktifkan', 403);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        $user->loadMissing('roles.permissions');
        $userRoles = $user->roles->pluck('name')->all();

        // Super admin bypasses permission checks
        if (in_array('super_admin', $userRoles, true)) {
            return $next($request);
        }

        $userPermissions = $user->roles->flatMap(fn ($role) => $role->permissions)->pluck('name')->all();

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return $next($request);
            }
        }

        return $this->errorResponse('Anda tidak memiliki izin yang dibutuhkan untuk tindakan ini', 403);
    }
}
