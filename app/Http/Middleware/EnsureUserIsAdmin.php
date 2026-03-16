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
     * Protects the entire admin route group (not just the root path).
     * Allows access if the user is a system admin OR has at least one RBAC role assigned.
     * Individual routes use the `permission` middleware for fine-grained access control.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized. Authentication required.');
        }

        // Allow admins unconditionally
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Allow RBAC users who have at least one role assigned (permission middleware
        // handles fine-grained access for each individual route)
        if ($user->roles()->exists()) {
            return $next($request);
        }

        abort(403, 'Unauthorized. Admin access required.');
    }
}
