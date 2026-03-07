<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

/**
 * Resource-level authorization for Content models.
 *
 * Delegates to AuthorizationService for permission checks.
 * Adds resource-specific logic: Authors can only edit their own content.
 */
class ContentPolicy
{
    public function __construct(
        private readonly AuthorizationService $authz,
    ) {}

    private function space(Content $content): Space
    {
        return $content->contentType->space;
    }

    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'content.read');
    }

    public function view(User $user, Content $content): bool
    {
        return $this->authz->can($user, 'content.read', $this->space($content));
    }

    public function create(User $user): bool
    {
        /** @var Space|null $space */
        $space = request()->attributes->get('active_space');

        return $this->authz->can($user, 'content.create', $space);
    }

    public function update(User $user, Content $content): bool
    {
        $space = $this->space($content);

        // Full update permission
        if ($this->authz->can($user, 'content.update', $space)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Content $content): bool
    {
        return $this->authz->can($user, 'content.delete', $this->space($content));
    }

    public function publish(User $user, Content $content): bool
    {
        return $this->authz->can($user, 'content.publish', $this->space($content));
    }

    public function unpublish(User $user, Content $content): bool
    {
        return $this->authz->can($user, 'content.unpublish', $this->space($content));
    }

    public function manageTypes(User $user): bool
    {
        /** @var Space|null $space */
        $space = request()->attributes->get('active_space');

        return $this->authz->can($user, 'content.type.manage', $space);
    }
}
