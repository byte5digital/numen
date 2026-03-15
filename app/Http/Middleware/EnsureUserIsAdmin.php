<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * For the dashboard (/admin), require admin role.
     * For other admin routes, just require authentication (specific routes use permission middleware).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized. Authentication required.');
        }

        // Dashboard requires admin role
        if ($request->path() === 'admin' || $request->path() === 'admin/') {
            if (! $user->isAdmin()) {
                abort(403, 'Unauthorized. Admin access required.');
            }
        }

        return $next($request);
    }
}
