<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PermissionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List users that have the given role.
     * Requires roles.manage permission.
     */
    public function roleUsers(Role $role, Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'roles.manage');

        $users = $role->users()->paginate(20);

        return response()->json([
            'data' => $users->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'space_id'   => $u->pivot->space_id,
                'created_at' => $u->created_at,
            ]),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
            ],
        ]);
    }

    /**
     * Assign a role to a user.
     * Requires users.roles.assign permission.
     * Anti-escalation: cannot assign a role with more permissions than self (unless has *).
     */
    public function assignRole(User $user, Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authz->authorize($actor, 'users.roles.assign');

        $data = $request->validate([
            'role_id'  => ['required', 'string', 'exists:roles,id'],
            'space_id' => ['nullable', 'string', 'exists:spaces,id'],
        ]);

        $role    = Role::findOrFail($data['role_id']);
        $spaceId = $data['space_id'] ?? null;

        // Anti-escalation: unless actor has *, they cannot assign a role
        // whose permissions are a strict superset of their own.
        $actorPermissions = $this->authz->userPermissions($actor, $spaceId);
        if (! in_array('*', $actorPermissions, true)) {
            $rolePermissions = $role->permissions ?? [];
            foreach ($rolePermissions as $perm) {
                if (! $this->authz->check($actor, $perm, $spaceId)) {
                    throw new PermissionDeniedException('users.roles.assign');
                }
            }
        }

        // Check for duplicate assignment
        $alreadyAssigned = $user->roles()
            ->wherePivot('role_id', $role->id)
            ->wherePivot('space_id', $spaceId)
            ->exists();

        if ($alreadyAssigned) {
            return response()->json(['error' => 'Role already assigned.'], 409);
        }

        $user->roles()->attach($role->id, ['space_id' => $spaceId]);

        $this->authz->log($actor, 'role.assign', $user, [
            'role_id'  => $role->id,
            'role'     => $role->slug,
            'space_id' => $spaceId,
        ]);

        return response()->json([
            'message'  => 'Role assigned',
            'user_id'  => $user->id,
            'role_id'  => $role->id,
            'space_id' => $spaceId,
        ], 201);
    }

    /**
     * Revoke a role from a user.
     * Requires users.roles.assign permission.
     */
    public function revokeRole(User $user, Role $role, Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authz->authorize($actor, 'users.roles.assign');

        $assigned = $user->roles()->where('role_id', $role->id)->exists();

        if (! $assigned) {
            return response()->json(['error' => 'Role not assigned to this user.'], 404);
        }

        $user->roles()->detach($role->id);

        $this->authz->log($actor, 'role.revoke', $user, [
            'role_id' => $role->id,
            'role'    => $role->slug,
        ]);

        return response()->json(['message' => 'Role revoked']);
    }

    /**
     * List roles assigned to a user, optionally filtered by space_id.
     * Requires roles.manage OR users.manage permission.
     */
    public function userRoles(User $user, Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $this->authz->check($actor, 'roles.manage') && ! $this->authz->check($actor, 'users.manage')) {
            throw new PermissionDeniedException('roles.manage');
        }

        $query = $user->roles()->withPivot('space_id');

        if ($spaceId = $request->query('space_id')) {
            $query->wherePivot('space_id', $spaceId);
        }

        $roles = $query->get()->map(fn (Role $r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'slug'        => $r->slug,
            'permissions' => $r->permissions,
            'space_id'    => $r->pivot->space_id,
        ]);

        return response()->json(['data' => $roles]);
    }
}
