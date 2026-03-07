<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Authorization\PermissionRegistrar;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __construct(
        private AuthorizationService $authz,
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * List all available permissions, grouped by category.
     * Requires roles.manage permission.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'roles.manage');

        return response()->json([
            'data' => $this->registrar->all(),
        ]);
    }
}
