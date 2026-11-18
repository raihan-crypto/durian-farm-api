<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Akun Anda dinonaktifkan', 403);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $userRoles = $user->roles->pluck('name')->all();

        // Super admin bypasses specific role checks
        if (in_array('super_admin', $userRoles, true)) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return $next($request);
            }
        }

        return $this->errorResponse('Anda tidak memiliki hak akses yang memadai', 403);
    }
}
