<?php

namespace App\Services;

use App\Exceptions\PermissionDeniedException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuthorizationService
{
    /**
     * Per-request permission cache: [userId.spaceId => [permission, ...]]
     */
    private array $cache = [];

    /**
     * Check whether a user has the given permission, optionally scoped to a space.
     * Also enforces token-level scoping when a Sanctum or ApiKey token is present.
     */
    public function check(User $user, string $permission, ?string $spaceId = null): bool
    {
        $cacheKey = $user->id.'.'.($spaceId ?? '__global__');

        if (! isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->resolvePermissions($user, $spaceId);
        }

        // Role-based check first
        if (! $this->permissionMatches($permission, $this->cache[$cacheKey])) {
            return false;
        }

        // Token scope check (additive guard — only active when a token is present)
        return $this->checkTokenScope(request(), $permission);
    }

    /**
     * Check if the authenticated request's token scopes allow this permission.
     * Called AFTER the user's role-based check passes.
     *
     * Returns true when:
     *  - No token is present (session auth) → no restriction
     *  - Token has wildcard ability '*' → allow all
     *  - Token ability includes the required permission → allow
     *
     * Returns false when:
     *  - Token is present and does NOT include the required permission
     */
    protected function checkTokenScope(Request $request, string $permission): bool
    {
        $user = $request->user();

        if (! $user) {
            return true;
        }

        // Sanctum personal access tokens
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();

            // Access token abilities array directly for proper wildcard expansion.
            // PersonalAccessToken stores abilities as a JSON-casted Eloquent attribute
            // (not a method), so we access it directly and fall back to an empty array.
            $abilities = (array) ($token->abilities ?? []);
            if (! empty($abilities)) {

                // Top-level wildcard '*' grants everything
                if (in_array('*', $abilities, true)) {
                    return true;
                }

                // Exact match
                if (in_array($permission, $abilities, true)) {
                    return true;
                }

                // Namespaced wildcard expansion: 'content.*' matches 'content.create', etc.
                $parts = explode('.', $permission);
                for ($i = count($parts) - 1; $i > 0; $i--) {
                    $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';
                    if (in_array($wildcard, $abilities, true)) {
                        return true;
                    }
                }

                // No matching ability found — deny
                return false;
            }
        }

        return true;
    }

    /**
     * Assert the user has the permission — throws PermissionDeniedException if not.
     *
     * @throws PermissionDeniedException
     */
    public function authorize(User $user, string $permission, ?string $spaceId = null): void
    {
        if (! $this->check($user, $permission, $spaceId)) {
            throw new PermissionDeniedException($permission);
        }
    }

    /**
     * Return the full set of effective permissions for the user (optionally scoped to a space).
     *
     * @return array<string>
     */
    public function userPermissions(User $user, ?string $spaceId = null): array
    {
        $cacheKey = $user->id.'.'.($spaceId ?? '__global__');

        if (! isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->resolvePermissions($user, $spaceId);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Write an audit log entry.
     */
    public function log(
        User $user,
        string $action,
        ?Model $resource = null,
        array $metadata = [],
    ): void {
        AuditLog::create([
            'user_id' => $user->id,
            'space_id' => null,
            'action' => $action,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Resolve the user's effective permissions by collecting permissions from all
     * applicable roles (global + space-scoped), then flattening and deduplicating.
     *
     * @return array<string>
     */
    private function resolvePermissions(User $user, ?string $spaceId): array
    {
        // Load roles with pivot if not already loaded
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        $permissions = [];

        foreach ($user->roles as $role) {
            $pivotSpace = $role->pivot->space_id ?? null;

            // Include global roles (null space) and roles for the requested space
            if ($pivotSpace === null || $pivotSpace === $spaceId) {
                foreach ($role->permissions ?? [] as $p) {
                    $permissions[] = $p;
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Check if $requested is satisfied by the $permissions array, supporting wildcards.
     *
     * Wildcard rules:
     *  - '*' in permissions → grants everything
     *  - 'content.*' in permissions → grants 'content.create', 'content.update', etc.
     *  - 'ai.model.*' in permissions → grants 'ai.model.opus', etc.
     */
    private function permissionMatches(string $requested, array $permissions): bool
    {
        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($requested, $permissions, true)) {
            return true;
        }

        // Build increasingly broad wildcard variants and check if any match
        $parts = explode('.', $requested);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';
            if (in_array($wildcard, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
