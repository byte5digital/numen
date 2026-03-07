<?php

namespace App\Http\Middleware;

use App\Models\Space;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active Space for the current request and stores it
 * on request attributes so downstream middleware and controllers can use it.
 *
 * Resolution order:
 *   1. Route parameter 'space' or 'space_id'
 *   2. X-Space-Id request header
 *   3. Session value 'active_space_id'
 *   4. null (global context)
 */
class ResolveActiveSpace
{
    public function handle(Request $request, Closure $next): Response
    {
        $space = $this->resolveSpace($request);

        $request->attributes->set('active_space', $space);

        return $next($request);
    }

    private function resolveSpace(Request $request): ?Space
    {
        // 1. Route parameter
        $spaceId = $request->route('space') ?? $request->route('space_id');

        if ($spaceId instanceof Space) {
            return $spaceId;
        }

        if (is_string($spaceId) && $spaceId !== '') {
            return Space::find($spaceId);
        }

        // 2. Header
        $headerSpaceId = $request->header('X-Space-Id');
        if ($headerSpaceId) {
            return Space::find($headerSpaceId);
        }

        // 3. Session (only on stateful requests — API routes may not have a session)
        try {
            $sessionSpaceId = $request->session()->get('active_space_id');
            if ($sessionSpaceId) {
                return Space::find($sessionSpaceId);
            }
        } catch (\RuntimeException) {
            // Session store not set (stateless API request) — skip
        }

        return null;
    }
}
