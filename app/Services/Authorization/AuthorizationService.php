<?php

namespace App\Services\Authorization;

use App\Models\Space;
use App\Models\User;

/**
 * Core authorization service. Resolves a user's effective permissions
 * from their assigned roles (space-scoped + global) and checks them.
 *
 * Results are cached per-request on the service instance.
 * No Redis/persistent cache — permission changes must be immediate.
 */
class AuthorizationService
{
    /** @var array<string, list<string>> in-memory per-request cache keyed by "userId:spaceId" */
    private array $cache = [];

    /**
     * Check whether the given user has a specific permission in the given space.
     *
     * @param  User  $user  The authenticated user
     * @param  string  $permission  Permission string (e.g. 'content.publish', 'ai.model.opus')
     * @param  Space|null  $space  The active space context (null = global check)
     */
    public function can(User $user, string $permission, ?Space $space = null): bool
    {
        $permissions = $this->resolvePermissions($user, $space);

        return $this->matchPermission($permission, $permissions);
    }

    /**
     * Resolve the full set of effective permissions for a user in a space.
     * Merges global role permissions with space-scoped role permissions.
     *
     * @return list<string>
     */
    public function resolvePermissions(User $user, ?Space $space = null): array
    {
        $cacheKey = $user->id.':'.($space !== null ? $space->id : 'global');

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $roles = $user->rolesForSpace($space);
        $permissions = [];

        foreach ($roles as $role) {
            foreach ($role->permissions ?? [] as $perm) {
                $permissions[] = $perm;
            }
        }

        $permissions = array_unique($permissions);

        $this->cache[$cacheKey] = $permissions;

        return $permissions;
    }

    /**
     * Resolve the effective AI limits for a user in a space.
     * Takes the most permissive value across all assigned roles.
     *
     * @return array{
     *     daily_generations: int,
     *     daily_image_generations: int,
     *     monthly_cost_limit_usd: float,
     *     allowed_models: list<string>,
     *     max_tokens_per_request: int,
     *     require_approval_above_cost_usd: float|null
     * }
     */
    public function resolveAiLimits(User $user, ?Space $space = null): array
    {
        $roles = $user->rolesForSpace($space);

        $dailyGen = 0;
        $dailyImageGen = 0;
        $monthlyCost = 0.0;
        $maxTokens = 0;
        $allowedModels = [];
        $approvalThreshold = 0.0; // 0 means "no approval threshold set yet"
        $hasThreshold = false;   // tracks whether any role has a threshold

        foreach ($roles as $role) {
            $ai = $role->ai_limits ?? [];

            $dailyGen = max($dailyGen, (int) ($ai['daily_generations'] ?? 0));
            $dailyImageGen = max($dailyImageGen, (int) ($ai['daily_image_generations'] ?? 0));
            $monthlyCost = max($monthlyCost, (float) ($ai['monthly_cost_limit_usd'] ?? 0.0));
            $maxTokens = max($maxTokens, (int) ($ai['max_tokens_per_request'] ?? 0));

            foreach ($ai['allowed_models'] ?? [] as $model) {
                if (! in_array($model, $allowedModels, true)) {
                    $allowedModels[] = $model;
                }
            }

            // Most permissive threshold = highest value, or null if any role has no threshold.
            if (isset($ai['require_approval_above_cost_usd'])) {
                $t = (float) $ai['require_approval_above_cost_usd'];
                if (! $hasThreshold) {
                    $approvalThreshold = $t;
                    $hasThreshold = true;
                } else {
                    $approvalThreshold = $t > $approvalThreshold ? $t : $approvalThreshold;
                }
            }
            // If key absent, this role has no approval requirement — leave threshold as-is
        }

        return [
            'daily_generations' => $dailyGen,
            'daily_image_generations' => $dailyImageGen,
            'monthly_cost_limit_usd' => $monthlyCost,
            'allowed_models' => $allowedModels,
            'max_tokens_per_request' => $maxTokens,
            'require_approval_above_cost_usd' => $hasThreshold ? $approvalThreshold : null,
        ];
    }

    /**
     * Clear the per-request permission cache (useful in tests or after role changes).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    // ── Internal ──────────────────────────────────────────────────────────

    /**
     * Check whether $permission is granted by the given permission set,
     * respecting wildcard expansion.
     *
     * @param  list<string>  $permissions
     */
    private function matchPermission(string $permission, array $permissions): bool
    {
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        if (in_array('*', $permissions, true)) {
            return true;
        }

        $parts = explode('.', $permission);
        if (count($parts) >= 2) {
            $domainWild = $parts[0].'.*';
            if (in_array($domainWild, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
