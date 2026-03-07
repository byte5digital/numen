<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Query-only interface for audit logs.
 *
 * Supports filtering by user_id, space_id, action, resource type, and date range.
 * Requires settings.system permission (admin-level access).
 */
class AuditLogController extends Controller
{
    /**
     * GET /api/v1/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('settings.system');

        $query = AuditLog::query()->latest('created_at');

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($spaceId = $request->query('space_id')) {
            $query->where('space_id', $spaceId);
        }

        if ($action = $request->query('action')) {
            $query->where('action', 'like', $action.'%');
        }

        if ($resourceType = $request->query('resource_type')) {
            $query->where('resource_type', $resourceType);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($until = $request->query('until')) {
            $query->where('created_at', '<=', $until);
        }

        $logs = $query->paginate(
            (int) $request->query('per_page', 50)
        );

        return response()->json($logs);
    }
}
