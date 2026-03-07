<?php

namespace App\Http\Middleware;

use App\Models\Space;
use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware for permission checks.
 *
 * Usage in routes:
 *   ->middleware('can:content.create')
 *   ->middleware('can:content.create,content.publish')  // AND logic
 *
 * Reads the active space from request attributes (set by ResolveActiveSpace).
 */
class CheckPermission
{
    public function __construct(
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * @param  string  ...$permissions  One or more required permissions (all must be granted — AND logic)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        /** @var Space|null $space */
        $space = $request->attributes->get('active_space');

        foreach ($permissions as $permission) {
            if (! $this->authz->can($user, $permission, $space)) {
                abort(403, "Forbidden. Required permission: {$permission}");
            }
        }

        return $next($request);
    }
}
