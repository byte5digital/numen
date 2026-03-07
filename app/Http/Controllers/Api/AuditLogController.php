<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List audit logs, filterable, paginated.
     * Requires audit.view permission.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'audit.view');

        $query = AuditLog::query()->orderByDesc('created_at');

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($spaceId = $request->query('space_id')) {
            $query->where('space_id', $spaceId);
        }

        if ($resourceType = $request->query('resource_type')) {
            $query->where('resource_type', $resourceType);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
            ],
        ]);
    }
}
