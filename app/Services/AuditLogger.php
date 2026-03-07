<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Convenience wrapper around AuditLog for common action logging.
 */
class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * Log an action to the audit trail.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $metadata = null,
        ?string $spaceId = null,
        ?string $userId = null,
    ): AuditLog {
        $actingUserId = $userId ?? (Auth::id() ? (string) Auth::id() : null);

        return AuditLog::create([
            'user_id' => $actingUserId,
            'space_id' => $spaceId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
        ]);
    }

    // ── Convenience methods ───────────────────────────────────────────────

    public function roleAssigned(string $roleId, string $targetUserId, ?string $spaceId = null): AuditLog
    {
        return $this->log('role.assigned', 'role', $roleId, [
            'target_user_id' => $targetUserId,
            'space_id' => $spaceId,
        ]);
    }

    public function roleRevoked(string $roleId, string $targetUserId, ?string $spaceId = null): AuditLog
    {
        return $this->log('role.revoked', 'role', $roleId, [
            'target_user_id' => $targetUserId,
            'space_id' => $spaceId,
        ]);
    }

    public function roleCreated(string $roleId, string $roleName): AuditLog
    {
        return $this->log('role.created', 'role', $roleId, ['name' => $roleName]);
    }

    public function roleUpdated(string $roleId, string $roleName, array $changes = []): AuditLog
    {
        return $this->log('role.updated', 'role', $roleId, [
            'name' => $roleName,
            'changes' => $changes,
        ]);
    }

    public function roleDeleted(string $roleId, string $roleName): AuditLog
    {
        return $this->log('role.deleted', 'role', $roleId, ['name' => $roleName]);
    }
}
