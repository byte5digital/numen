<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AuditLog> $auditLogs
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('space_id')
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ── Permission Helpers ────────────────────────────────────────────────

    /**
     * Get all roles for this user in the given space (plus global roles).
     * Always includes global (space_id = null) roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function rolesForSpace(?Space $space = null): \Illuminate\Database\Eloquent\Collection
    {
        $collection = $this->roles()
            ->where(function ($q) use ($space) {
                $q->whereNull('role_user.space_id');

                if ($space !== null) {
                    $q->orWhere('role_user.space_id', $space->id);
                }
            })
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $collection */
        return $collection;
    }

    /**
     * Assign a role to this user, optionally scoped to a space.
     */
    public function assignRole(Role $role, ?Space $space = null): void
    {
        $this->roles()->syncWithoutDetaching([
            $role->id => ['space_id' => $space?->id],
        ]);
    }

    /**
     * Revoke a role from this user, optionally scoped to a space.
     */
    public function revokeRole(Role $role, ?Space $space = null): void
    {
        $this->roles()
            ->wherePivot('space_id', $space?->id)
            ->detach($role->id);
    }

    /**
     * Convenience: is this user a system Admin (has * permission globally)?
     */
    public function isAdmin(): bool
    {
        return $this->roles()
            ->whereNull('role_user.space_id')
            ->where('roles.slug', 'admin')
            ->exists();
    }
}
