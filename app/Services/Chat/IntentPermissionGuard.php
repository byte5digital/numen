<?php

namespace App\Services\Chat;

use App\Exceptions\PermissionDeniedException;
use App\Models\Space;
use App\Models\User;

/**
 * Guards intent execution by checking user permissions before any action is taken.
 *
 * Maps intent actions to existing permission identifiers used by User::hasPermission()
 * and User::isAdmin(). Throws PermissionDeniedException (extends AuthorizationException)
 * when the user lacks the required permission.
 */
class IntentPermissionGuard
{
    /**
     * Maps intent actions to the permission string required.
     *
     * @var array<string, string>
     */
    private const ACTION_PERMISSIONS = [
        'content.query' => 'content.view',
        'content.create' => 'content.create',
        'content.update' => 'content.update',
        'content.delete' => 'content.delete',
        'content.publish' => 'content.publish',
        'content.unpublish' => 'content.publish',
        'pipeline.trigger' => 'pipeline.trigger',
        'query.generic' => '',
    ];

    /**
     * Check if the user is permitted to execute the given intent in the given space.
     *
     * @param  array<string, mixed>  $intent
     *
     * @throws PermissionDeniedException
     */
    public function check(array $intent, User $user, Space $space): bool
    {
        $action = $intent['action'] ?? 'query.generic';

        // Admins bypass all permission checks
        if ($user->isAdmin()) {
            return true;
        }

        // query.generic requires no special permission
        if ($action === 'query.generic') {
            return true;
        }

        $permission = self::ACTION_PERMISSIONS[$action] ?? null;

        // Unknown action — deny by default
        if ($permission === null) {
            throw new PermissionDeniedException("chat.{$action}");
        }

        if (! $user->hasPermission($permission, $space->id)) {
            throw new PermissionDeniedException($permission);
        }

        return true;
    }
}
