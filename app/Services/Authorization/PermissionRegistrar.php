<?php

namespace App\Services\Authorization;

/**
 * Centralized permission taxonomy — single source of truth for all valid permissions.
 *
 * The PermissionRegistrar defines the canonical list of available permissions,
 * grouped by domain (content, users, roles, audit, etc.). This list is used for:
 *  - Validating permission strings when creating/updating roles
 *  - Populating the role editor UI (permission checkboxes)
 *  - API endpoint GET /permissions that returns the full taxonomy
 *
 * Adding new permissions:
 *  1. Update the all() method to add the new domain.action pair
 *  2. Update RBAC_GUIDE.md with the new permission description
 *  3. Update openapi.yaml to document the endpoint
 *  4. No database migration needed — permissions are strings
 */
class PermissionRegistrar
{
    /**
     * Returns the full canonical permission taxonomy grouped by domain.
     * This is the single source of truth for all valid permissions in the system.
     *
     * Format: domain => [permission => description, ...]
     * Structure:
     *  - 'content' → content creation, reading, publishing, etc.
     *  - 'users' → user management, role assignment, invitations
     *  - 'roles' → role CRUD operations
     *  - 'spaces' → space management and deletion
     *  - 'audit' → audit log viewing
     *  - 'settings' → system and API token settings
     *  - 'ai' → AI generation and model access
     *  - 'components' → component type management
     *  - 'pipelines' → pipeline execution and approval
     *  - 'personas' → persona viewing
     *
     * Used by:
     *  - Role editor (permission checkboxes)
     *  - Permission validation (on role create/update)
     *  - API endpoint GET /api/v1/permissions
     *
     * @return array<string, array<string, string>> Domain => [permission => description]
     */
    public function all(): array
    {
        return [
            'content' => [
                'content.read' => 'Read published content',
                'content.create' => 'Create new content',
                'content.update' => 'Edit existing content',
                'content.delete' => 'Delete content',
                'content.publish' => 'Publish / unpublish content',
                'content.restore' => 'Restore deleted content',
            ],
            'users' => [
                'users.manage' => 'Manage user accounts',
                'users.roles.assign' => 'Assign and revoke roles',
                'users.invite' => 'Invite new users',
                'users.delete' => 'Delete user accounts',
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
                'settings.general' => 'Manage general settings',
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
            'search' => [
                'search.admin' => 'Manage search settings, synonyms, and promoted results',
            ],
            ],
        ];
    }

    /**
     * Returns a flat list of all valid permission slugs (for validation, etc).
     *
     * This is useful for:
     *  - Validating permission strings in role create/update requests
     *  - Building permission selector dropdowns
     *  - Checking if a given string is a valid permission
     *
     * @return array<string> Flat array of permission strings, e.g. ['content.create', 'content.read', ...]
     */
    public function allFlat(): array
    {
        return array_keys(array_merge(...array_values($this->all())));
    }

    /**
     * Check if a given permission string is valid.
     *
     * A permission is valid if:
     *  - It's the wildcard `*` (grants everything)
     *  - It's in the canonical list returned by allFlat()
     *
     * Used in role validation — when creating or updating a role, all permissions in
     * the request must pass this check. Invalid permissions are rejected.
     *
     * @param  string  $permission  The permission to validate (e.g. 'content.publish')
     * @return bool True if the permission is valid
     */
    public function isValid(string $permission): bool
    {
        if ($permission === '*') {
            return true;
        }

        return in_array($permission, $this->allFlat(), true);
    }
}
