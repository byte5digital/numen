<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions ?? [],
                'ai_limits' => $role->ai_limits,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Edit', [
            'role' => null,
            'allPermissions' => $this->permissionRegistrar->grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'ai_limits' => $this->buildAiLimits($data),
            'is_system' => false,
        ]);

        $this->audit->roleCreated($role->id, $role->name);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions ?? [],
                'ai_limits' => $role->ai_limits,
                'is_system' => $role->is_system,
            ],
            'allPermissions' => $this->permissionRegistrar->grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role);

        $before = [
            'permissions' => $role->permissions,
            'ai_limits' => $role->ai_limits,
        ];

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'ai_limits' => $this->buildAiLimits($data),
        ]);

        $this->audit->roleUpdated($role->id, $role->name, [
            'before' => $before,
            'after' => ['permissions' => $role->permissions, 'ai_limits' => $role->ai_limits],
        ]);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Cannot delete a role that has users assigned to it.']);
        }

        $name = $role->name;
        $id = $role->id;

        // Hard delete (force bypass audit log model guard — use DB directly for the audit)
        $role->users()->detach();
        \Illuminate\Support\Facades\DB::table('roles')->where('id', $role->id)->delete();

        $this->audit->roleDeleted($id, $name);

        return redirect()->route('admin.roles.index')->with('success', "Role \"{$name}\" deleted.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?Role $ignoreRole = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'ai_daily_generations' => ['nullable', 'integer', 'min:0'],
            'ai_daily_image_generations' => ['nullable', 'integer', 'min:0'],
            'ai_monthly_cost_limit_usd' => ['nullable', 'numeric', 'min:0'],
            'ai_max_tokens_per_request' => ['nullable', 'integer', 'min:0'],
            'ai_allowed_models' => ['nullable', 'array'],
            'ai_allowed_models.*' => ['string'],
            'ai_require_approval_above_cost_usd' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Build the ai_limits JSON column from validated flat fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function buildAiLimits(array $data): ?array
    {
        $hasAiData =
            isset($data['ai_daily_generations']) ||
            isset($data['ai_daily_image_generations']) ||
            isset($data['ai_monthly_cost_limit_usd']) ||
            isset($data['ai_max_tokens_per_request']) ||
            isset($data['ai_allowed_models']);

        if (! $hasAiData) {
            return null;
        }

        return [
            'daily_generations' => (int) ($data['ai_daily_generations'] ?? 0),
            'daily_image_generations' => (int) ($data['ai_daily_image_generations'] ?? 0),
            'monthly_cost_limit_usd' => (float) ($data['ai_monthly_cost_limit_usd'] ?? 0.0),
            'allowed_models' => $data['ai_allowed_models'] ?? [],
            'max_tokens_per_request' => (int) ($data['ai_max_tokens_per_request'] ?? 0),
            'require_approval_above_cost_usd' => isset($data['ai_require_approval_above_cost_usd'])
                ? (float) $data['ai_require_approval_above_cost_usd']
                : null,
        ];
    }
}
