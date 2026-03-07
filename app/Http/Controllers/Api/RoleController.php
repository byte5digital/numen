<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List all roles (optionally scoped to a space).
     * Requires roles.manage permission.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'roles.manage');

        $query = Role::query()->orderBy('name');

        if ($spaceId = $request->query('space_id')) {
            $query->where(function ($q) use ($spaceId) {
                $q->whereNull('space_id')->orWhere('space_id', $spaceId);
            });
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Create a new (custom) role.
     * Requires roles.manage permission.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'roles.manage');

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string'],
            'space_id'    => ['nullable', 'string', 'exists:spaces,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
            'ai_limits'   => ['nullable', 'array'],
        ]);

        $data['slug']        = $data['slug'] ?? Str::slug($data['name']);
        $data['permissions'] = $data['permissions'] ?? [];
        $data['is_system']   = false;

        $role = Role::create($data);

        return response()->json(['data' => $role], 201);
    }

    /**
     * Update a role's name, description, or permissions.
     * Requires roles.manage permission.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $user = $request->user();

        if ($role->space_id) {
            // Space-scoped role — enforce permission within that space only
            $this->authz->authorize($user, 'roles.manage', $role->space_id);

            // Also verify the user actually belongs to that space
            $hasSpaceAccess = $user->roles()
                ->where(function ($q) use ($role) {
                    $q->whereNull('role_user.space_id')
                        ->orWhere('role_user.space_id', $role->space_id);
                })
                ->exists();

            if (! $hasSpaceAccess) {
                return response()->json(['error' => 'Forbidden', 'message' => 'No access to this space.'], 403);
            }
        } else {
            // Global/system role — requires global roles.manage (no space restriction)
            $this->authz->authorize($user, 'roles.manage');

            // Global roles may only be modified by users with unrestricted (global) roles.manage
            $hasGlobalPermission = $user->roles()
                ->whereNull('role_user.space_id')
                ->get()
                ->contains(fn ($r) => in_array('*', $r->permissions ?? [], true)
                    || in_array('roles.manage', $r->permissions ?? [], true));

            if (! $hasGlobalPermission) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Modifying global roles requires a global roles.manage permission.',
                ], 403);
            }
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
            'ai_limits'   => ['nullable', 'array'],
        ]);

        $role->update($data);

        $this->authz->log($user, 'role.update', $role, [
            'updated_fields' => array_keys($data),
        ]);

        return response()->json(['data' => $role->fresh()]);
    }

    /**
     * Delete a role.
     * System roles (is_system = true) cannot be deleted — returns 422.
     * Requires roles.manage permission.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authz->authorize($request->user(), 'roles.manage');

        if ($role->is_system) {
            return response()->json([
                'error'   => 'System roles cannot be deleted.',
                'role'    => $role->slug,
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
