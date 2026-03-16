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

        // 2. X-Space-Id header
        if (! $space) {
            $spaceIdHeader = $request->header('X-Space-Id');
            if ($spaceIdHeader) {
                $space = Space::find($spaceIdHeader);
            }
        }

        // 3. First accessible space (MVP: just first space)
        if (! $space) {
            $space = Space::first();
        }

        // 4. Ultimate fallback
        if (! $space) {
            $space = Space::first();
        }

        // Bind to request attributes and persist in session
        $request->attributes->set('space', $space);
        if ($space) {
            session(['current_space_id' => $space->id]);
        }

        return $next($request);
    }
}
