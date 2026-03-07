<?php

namespace App\Services\Authorization;

use App\Models\AuditLog;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes immutable audit log entries for auditable actions.
 *
 * Usage:
 *   AuditLogger::log(
 *       action: 'content.publish',
 *       resource: $content,
 *       metadata: ['version' => 3],
 *       user: auth()->user(),
 *       space: $activeSpace,
 *   );
 */
class AuditLogger
{
    public function __construct(
        private readonly ?Request $request = null,
    ) {}

    /**
     * Write a single audit log entry.
     *
     * @param  string  $action  e.g. 'content.publish', 'role.assign', 'ai.generation'
     * @param  Model|null  $resource  Eloquent model for polymorphic resource reference
     * @param  array<string, mixed>  $metadata  Additional context
     * @param  User|null  $user  Authenticated user (null for system actions)
     * @param  Space|null  $space  Active space context
     */
    public function log(
        string $action,
        ?Model $resource = null,
        array $metadata = [],
        ?User $user = null,
        ?Space $space = null,
    ): AuditLog {
        $log = new AuditLog([
            'user_id' => $user?->id,
            'space_id' => $space?->id,
            'action' => $action,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->getKey() ? (string) $resource->getKey() : null,
            'metadata' => $metadata ?: null,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
        ]);

        $log->save();

        return $log;
    }

    /**
     * Static convenience method — resolves the service from the container.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function write(
        string $action,
        ?Model $resource = null,
        array $metadata = [],
        ?User $user = null,
        ?Space $space = null,
    ): AuditLog {
        return app(self::class)->log($action, $resource, $metadata, $user, $space);
    }
}
