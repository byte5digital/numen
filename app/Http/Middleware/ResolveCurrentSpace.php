<?php

namespace App\Http\Middleware;

use App\Models\Space;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentSpace
{
    /**
     * Handle an incoming request.
     *
     * Resolution order:
     *   1. Session-stored space ID
     *   2. X-Space-Id header (admin users only — prevents IDOR)
     *   3. First space (chronological fallback)
     *
     * Aborts with 503 if no space can be resolved at all.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $space = null;

        // 1. Session
        $sessionSpaceId = session('current_space_id');
        if ($sessionSpaceId) {
            $space = Space::find($sessionSpaceId);
        }

        // 2. X-Space-Id header — admin users only (IDOR guard)
        if (! $space) {
            $spaceIdHeader = $request->header('X-Space-Id');
            if ($spaceIdHeader && $request->user()?->isAdmin()) {
                $space = Space::find($spaceIdHeader);
            }
        }

        // 3. First space fallback
        if (! $space) {
            $space = Space::first();
        }

        // Abort if still no space — no null allowed through
        if (! $space) {
            abort(503, 'No space configured. Please create a space first.');
        }

        // Bind to request attributes and persist in session
        $request->attributes->set('space', $space);
        session(['current_space_id' => $space->id]);

        return $next($request);
    }
}
