<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

/**
 * Resource-level authorization for Space models.
 */
class SpacePolicy
{
    public function __construct(
        private readonly AuthorizationService $authz,
    ) {}

    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can see spaces they have access to
    }

    public function view(User $user, Space $space): bool
    {
        return $this->authz->can($user, 'spaces.switch', $space)
            || $this->authz->can($user, 'spaces.manage', $space);
    }

    public function create(User $user): bool
    {
        return $this->authz->can($user, 'spaces.manage');
    }

    public function update(User $user, Space $space): bool
    {
        return $this->authz->can($user, 'spaces.manage', $space);
    }

    public function delete(User $user, Space $space): bool
    {
        return $this->authz->can($user, 'spaces.manage', $space);
    }
}
