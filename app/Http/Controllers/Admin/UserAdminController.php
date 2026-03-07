<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('roles')->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('slug')->toArray(),
                'created_at' => $user->created_at->format('Y-m-d'),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::whereNull('space_id')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['nullable', 'ulid', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['role_id'])) {
            $user->roles()->attach($data['role_id'], ['space_id' => null]);
        }

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => $user->roles->pluck('id')->toArray(),
            ],
            'roles' => Role::whereNull('space_id')->orderBy('name')->get(['id', 'name', 'slug', 'description']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['ulid', 'exists:roles,id'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        // Sync global roles (space_id = null)
        if (isset($data['role_ids'])) {
            $pivotData = [];
            foreach ($data['role_ids'] as $roleId) {
                $pivotData[$roleId] = ['space_id' => null];
            }
            // Sync only global roles (preserve space-scoped role assignments)
            $user->roles()->wherePivotNull('space_id')->sync($pivotData);
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        // Prevent deleting the last admin
        if ($user->isAdmin()) {
            $adminRole = Role::where('slug', 'admin')->whereNull('space_id')->first();
            $adminCount = $adminRole ? $adminRole->users()->count() : 0;
            if ($adminCount <= 1) {
                return back()->withErrors(['user' => 'Cannot delete the last admin.']);
            }
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
