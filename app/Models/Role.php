<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string|null $space_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array $permissions
 * @property array|null $ai_limits
 * @property bool $is_system
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space|null $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 */
class Role extends Model
{
    use HasUlids;

    protected $fillable = [
        'space_id',
        'name',
        'slug',
        'description',
        'permissions',
        'ai_limits',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'ai_limits' => 'array',
        'is_system' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('space_id')
            ->withTimestamps();
    }

    /**
     * Check if this role has the given permission (supports wildcards).
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Wildcard expansion: 'content.*' matches 'content.create', 'content.read', etc.
        $parts = explode('.', $permission);
        for ($i = count($parts) - 1; $i >= 1; $i--) {
            $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';
            if (in_array($wildcard, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
