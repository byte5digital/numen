<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'editor', // Legacy column — kept for backward compat
        ];
    }

    /**
     * Assign the 'admin' role to the created user (via RBAC).
     * Requires the roles table to be seeded (use RoleSeeder in setUp).
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('slug', 'admin')->whereNull('space_id')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['space_id' => null],
                ]);
            }
        });
    }

    /**
     * Assign the 'editor' role to the created user (via RBAC).
     */
    public function editor(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('slug', 'editor')->whereNull('space_id')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['space_id' => null],
                ]);
            }
        });
    }

    /**
     * Assign the 'author' role to the created user (via RBAC).
     */
    public function author(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('slug', 'author')->whereNull('space_id')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['space_id' => null],
                ]);
            }
        });
    }

    /**
     * Assign the 'viewer' role to the created user (via RBAC).
     */
    public function viewer(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('slug', 'viewer')->whereNull('space_id')->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['space_id' => null],
                ]);
            }
        });
    }
}
