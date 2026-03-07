<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property string $key_hash
 * @property array $scopes
 * @property array|null $permissions
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 */
class ApiKey extends Model
{
    use HasUlids;

    protected $fillable = ['space_id', 'name', 'key_hash', 'scopes', 'permissions', 'expires_at', 'last_used_at'];

    protected $casts = [
        'scopes' => 'array',
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Check whether this API key has the given permission.
     * Permissions on the key act as a ceiling — the intersection with the
     * creating user's roles is checked at runtime by AuthorizationService.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        $parts = explode('.', $permission);
        if (count($parts) >= 2 && in_array($parts[0].'.*', $permissions, true)) {
            return true;
        }

        return false;
    }
}
