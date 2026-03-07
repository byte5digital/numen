<?php

namespace App\Services\Authorization;

class PermissionRegistrar
{
    /**
     * Returns the full canonical permission taxonomy.
     * This is the single source of truth for all valid permissions.
     * Used by admin UI, role editor, and validation.
     */
    public function all(): array
    {
        return [
            'content' => [
                'content.read'    => 'Read published content',
                'content.create'  => 'Create new content',
                'content.update'  => 'Edit existing content',
                'content.delete'  => 'Delete content',
                'content.publish' => 'Publish / unpublish content',
                'content.restore' => 'Restore deleted content',
            ],
            'users' => [
                'users.manage'       => 'Manage user accounts',
                'users.roles.assign' => 'Assign and revoke roles',
                'users.invite'       => 'Invite new users',
                'users.delete'       => 'Delete user accounts',
            ],
            'roles' => [
                'roles.manage' => 'Create, edit, and delete roles',
            ],
            'spaces' => [
                'spaces.manage' => 'Create and configure spaces',
                'spaces.delete' => 'Delete spaces',
            ],
            'audit' => [
                'audit.view' => 'View audit logs',
            ],
            'settings' => [
                'settings.general'    => 'Manage general settings',
                'settings.api_tokens' => 'Manage API tokens',
            ],
            'ai' => [
                'ai.generate' => 'Use AI content generation',
            ],
            'components' => [
                'component.manage' => 'Register and update custom component types',
            ],
            'pipelines' => [
                'pipeline.approve' => 'Approve pending pipeline runs for publication',
            ],
            'personas' => [
                'persona.view' => 'View persona configurations',
            ],
        ];
    }

    /**
     * Returns a flat list of all permission slugs (for validation etc.)
     */
    public function allFlat(): array
    {
        return array_keys(array_merge(...array_values($this->all())));
    }

    /**
     * Returns true if the given permission slug is valid.
     */
    public function isValid(string $permission): bool
    {
        if ($permission === '*') {
            return true;
        }

        return in_array($permission, $this->allFlat(), true);
    }
}
