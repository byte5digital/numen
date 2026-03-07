<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\Authorization\PermissionRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRUD management for Roles.
 *
 * All write endpoints require users.roles.manage permission.
 * Role assignment endpoints require users.roles.assign permission.
 * System roles cannot be deleted.
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
        private readonly AuditLogger $auditor,
    ) {}

    /**
     * GET /api/v1/roles
     * List roles (optionally space-scoped via X-Space-Id header).
     */
    public function index(Request $request): JsonResponse
    {
        $spaceId = $request->attributes->get('active_space')?->id;

        $roles = Role::query()
            ->when($spaceId, fn ($q) => $q->where(function ($q) use ($spaceId) {
                $q->whereNull('space_id')->orWhere('space_id', $spaceId);
            }), fn ($q) => $q->whereNull('space_id'))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
    }

    /**
     * POST /api/v1/roles
     * Create a custom role.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('users.roles.manage');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'ai_limits' => 'nullable|array',
            'space_id' => 'nullable|ulid|exists:spaces,id',
        ]);

        $this->validatePermissions($data['permissions']);

        $role = Role::create([
            'id' => Str::ulid()->toBase32(),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'],
            'ai_limits' => $data['ai_limits'] ?? null,
            'space_id' => $data['space_id'] ?? null,
            'is_system' => false,
        ]);

        $this->auditor->log(
            action: 'role.create',
            resource: $role,
            metadata: ['name' => $role->name],
            user: $request->user(),
            space: $request->attributes->get('active_space'),
        );

        return response()->json(['data' => $role], 201);
    }

    /**
     * PUT /api/v1/roles/{role}
     * Update a role's permissions and limits.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('users.roles.manage');

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string',
            'ai_limits' => 'nullable|array',
        ]);

        if (isset($data['permissions'])) {
            $this->validatePermissions($data['permissions']);
        }

        $role->update($data);

        $this->auditor->log(
            action: 'role.update',
            resource: $role,
            metadata: ['changed' => array_keys($data)],
            user: $request->user(),
            space: $request->attributes->get('active_space'),
        );

        return response()->json(['data' => $role]);
    }

    /**
     * DELETE /api/v1/roles/{role}
     * Delete a custom role (system roles cannot be deleted).
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('users.roles.manage');

        if ($role->is_system) {
            return response()->json(['error' => 'System roles cannot be deleted.'], 422);
        }

        $this->auditor->log(
            action: 'role.delete',
            resource: $role,
            metadata: ['name' => $role->name],
            user: $request->user(),
            space: $request->attributes->get('active_space'),
        );

        $role->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/roles/{role}/users
     * List users assigned to a role.
     */
    public function users(Role $role): JsonResponse
    {
        $users = $role->users()->get(['users.id', 'users.name', 'users.email']);

        return response()->json(['data' => $users]);
    }

    /**
     * POST /api/v1/users/{user}/roles
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.roles.assign');

        $data = $request->validate([
            'role_id' => 'required|ulid|exists:roles,id',
            'space_id' => 'nullable|ulid|exists:spaces,id',
        ]);

        $user->roles()->syncWithoutDetaching([
            $data['role_id'] => ['space_id' => $data['space_id'] ?? null],
        ]);

        $this->auditor->log(
            action: 'role.assign',
            resource: $user,
            metadata: ['role_id' => $data['role_id'], 'space_id' => $data['space_id'] ?? null],
            user: $request->user(),
            space: $request->attributes->get('active_space'),
        );

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/v1/users/{user}/roles/{role}
     * Revoke a role from a user.
     */
    public function revokeRole(Request $request, User $user, Role $role): JsonResponse
    {
        $this->authorize('users.roles.assign');

        $user->roles()->detach($role->id);

        $this->auditor->log(
            action: 'role.revoke',
            resource: $user,
            metadata: ['role_id' => $role->id],
            user: $request->user(),
            space: $request->attributes->get('active_space'),
        );

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/permissions
     * List all available permissions from the registrar.
     */
    public function permissions(): JsonResponse
    {
        return response()->json(['data' => $this->registrar->grouped()]);
    }

    /**
     * GET /api/v1/ai/budget/{user}
     * Get AI budget usage for a user.
     */
    public function aiBudget(User $user): JsonResponse
    {
        // TODO: Implement detailed budget usage query
        return response()->json([
            'user_id' => $user->id,
            'message' => 'Budget usage endpoint — full implementation pending BudgetGuard integration.',
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function validatePermissions(array $permissions): void
    {
        foreach ($permissions as $perm) {
            if (! $this->registrar->isValid($perm)) {
                throw ValidationException::withMessages([
                    'permissions' => ["Unknown permission: {$perm}"],
                ]);
            }
        }
    }
}
