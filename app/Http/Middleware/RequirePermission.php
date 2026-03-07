<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequirePermission Middleware — enforces role-based access control on routes.
 *
 * This middleware checks that the authenticated user has the required permission
 * before allowing the request to proceed. Unauthorized requests are denied with
 * a 403 Forbidden response, and the denial attempt is logged to the audit trail.
 *
 * Usage in routes (registered as 'permission'):
 *   Route::post('/content', Controller::class)
 *     ->middleware('permission:content.create');
 *
 *   Route::put('/content/{id}', Controller::class)
 *     ->middleware('permission:content.update');
 *
 *   Route::post('/content', Controller::class)
 *     ->middleware('permission:content.create,space-id-123');  // space-scoped check
 *
 * How it works:
 *  1. Extract user from request
 *  2. If no user, return 401 Unauthenticated
 *  3. Check permission via AuthorizationService::check()
 *  4. If denied, log the denial to audit_logs and return 403 Forbidden
 *  5. If allowed, proceed to next middleware/controller
 */
class RequirePermission
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * Handle an incoming request by checking permission.
     *
     * @param  Request  $request  The HTTP request
     * @param  Closure  $next  The next middleware
     * @param  string  $permission  The required permission (e.g. 'content.publish')
     * @param  string|null  $spaceId  Optional space context for scoped permission check
     * @return Response The response (403 if denied, next middleware if allowed)
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
