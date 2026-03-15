<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentPipeline;
use App\Models\User;

class ContentPipelinePolicy
{
    /**
     * Admins bypass all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function trigger(User $user, ContentPipeline $pipeline): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }

    public function approve(User $user, ContentPipeline $pipeline): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }
}
