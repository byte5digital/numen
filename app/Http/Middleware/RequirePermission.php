<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('permission:content.publish')
     *   ->middleware('permission:content.publish,spaceId-here')
     *
     * The middleware checks the authenticated user's effective permissions.
     * On failure it returns a 403 JSON payload and logs the denied attempt.
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $spaceId = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'required' => $permission,
            ], 401);
        }

        if (! $this->authz->check($user, $permission, $spaceId)) {
            // Log the denied attempt
            AuditLog::create([
                'user_id' => $user->id,
                'space_id' => $spaceId,
                'action' => 'permission.denied',
                'metadata' => [
                    'required' => $permission,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            return response()->json([
                'error' => 'Forbidden',
                'required' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
