<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * List all users. Requires users.manage permission.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'users.manage');

        $users = User::orderBy('name')->get()->map(fn (User $u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'created_at' => $u->created_at,
        ]);

        return response()->json(['data' => $users]);
    }

    /**
     * Create a new user. Requires users.manage permission.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'users.manage');

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->authz->log($request->user(), 'user.create', $user);

        return response()->json(['data' => $user], 201);
    }

    /**
     * Update a user. Requires users.manage permission.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authz->authorize($request->user(), 'users.manage');

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        $this->authz->log($request->user(), 'user.update', $user);

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * Delete a user. Requires users.manage permission.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authz->authorize($request->user(), 'users.manage');

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
