<?php

namespace App\Services\Authorization;

/**
 * Single source of truth for all valid permission strings in Numen.
 *
 * Permissions follow a domain.action or domain.sub.action pattern.
 * This class is used by:
 *  - Admin UI (permission editor checklist)
 *  - Tests (to verify all permissions are handled)
 *  - AuthorizationService (wildcard expansion reference)
 */
class PermissionRegistrar
{
    /**
     * Returns the full taxonomy of all registered permissions,
     * grouped by domain for display purposes.
     *
     * @return array<string, list<string>>
     */
    public function grouped(): array
    {
        return [
            'content' => [
                'content.create',
                'content.read',
                'content.update',
                'content.delete',
                'content.publish',
                'content.unpublish',
                'content.type.manage',
            ],
            'pipeline' => [
                'pipeline.run',
                'pipeline.configure',
                'pipeline.approve',
                'pipeline.reject',
            ],
            'media' => [
                'media.upload',
                'media.delete',
                'media.organize',
                'media.read',
            ],
            'users' => [
                'users.manage',
                'users.roles.assign',
                'users.roles.manage',
            ],
            'settings' => [
                'settings.system',
                'settings.personas',
                'settings.api_tokens',
                'settings.webhooks',
            ],
            'spaces' => [
                'spaces.manage',
                'spaces.switch',
            ],
            'ai' => [
                'ai.generate',
                'ai.model.opus',
                'ai.model.sonnet',
                'ai.model.haiku',
                'ai.image.generate',
                'ai.budget.unlimited',
                'ai.persona.configure',
            ],
        ];
    }

    /**
     * Returns a flat list of all registered permissions.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->grouped()));
    }

    /**
     * Check whether a given permission string is a known registered permission
     * (including wildcard patterns like * and content.*).
     */
    public function isValid(string $permission): bool
    {
        if ($permission === '*') {
            return true;
        }

        // Allow domain wildcards like "content.*"
        if (str_ends_with($permission, '.*')) {
            $domain = substr($permission, 0, -2);

            return array_key_exists($domain, $this->grouped());
        }

        return in_array($permission, $this->all(), true);
    }
}
