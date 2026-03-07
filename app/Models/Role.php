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

    // ── Relationships ─────────────────────────────────────────────────────

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

    // ── Permission Helpers ────────────────────────────────────────────────

    /**
     * Check whether this role grants the given permission string,
     * including wildcard expansion (* and domain.* patterns).
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        // Direct match
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Global wildcard
        if (in_array('*', $permissions, true)) {
            return true;
        }

        // Domain wildcard: "content.*" matches "content.create", etc.
        $parts = explode('.', $permission);
        if (count($parts) >= 2) {
            $domainWild = $parts[0].'.*';
            if (in_array($domainWild, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
