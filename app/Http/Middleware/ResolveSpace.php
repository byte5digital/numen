<?php

namespace App\Http\Middleware;

use App\Models\Space;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSpace
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $space = null;

        // 1. Check X-Space-Id header
        $spaceIdHeader = $request->header('X-Space-Id');
        if ($spaceIdHeader) {
            $space = Space::find($spaceIdHeader);
        }

        // 2. Check session
        if (! $space) {
            $sessionSpaceId = session('current_space_id');
            if ($sessionSpaceId) {
                $space = Space::find($sessionSpaceId);
            }
        }

        // 3. Fallback to first space
        if (! $space) {
            $space = Space::orderBy('created_at', 'asc')->first();
        }

        // 4. No space configured
        if (! $space) {
            abort(503, 'No space configured');
        }

        // 5. Bind and persist in session
        app()->instance('current_space', $space);
        session(['current_space_id' => $space->id]);

        return $next($request);
    }
}
