<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

/**
 * Policy for Content versioning operations.
 *
 * Space isolation: if a user has a space_id assigned, they can only
 * access content within that space. Admins are not space-scoped.
 */
class ContentPolicy
{
    /**
     * Admins bypass all policy checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Space access check: returns false if the user is scoped to a different space.
     */
    private function inSpace(User $user, Content $content): bool
    {
        // If no space restriction, user can access any space
        if ($user->space_id === null) {
            return true;
        }

        return $user->space_id === $content->space_id;
    }

    /**
     * View versioning details (index, show, diff).
     */
    public function view(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }

    /**
     * Create or update a draft version.
     */
    public function modify(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }

    /**
     * Publish a version.
     */
    public function publish(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }

    /**
     * Rollback to a historical version (creates a new draft — does NOT auto-publish).
     */
    public function rollback(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }

    /**
     * Schedule a version for future publishing.
     */
    public function schedule(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }

    /**
     * Cancel a scheduled publish.
     */
    public function cancelSchedule(User $user, Content $content): bool
    {
        return $user->role === 'editor' && $this->inSpace($user, $content);
    }
}
