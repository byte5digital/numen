<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string $version
 * @property string|null $description
 * @property array<string, mixed> $manifest
 * @property string $status
 * @property \Carbon\Carbon|null $installed_at
 * @property \Carbon\Carbon|null $activated_at
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PluginSetting> $settings
 */
class Plugin extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'version',
        'description',
        'manifest',
        'status',
        'installed_at',
        'activated_at',
        'error_message',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'manifest' => 'array',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function settings(): HasMany
    {
        return $this->hasMany(PluginSetting::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInstalled(Builder $query): Builder
    {
        return $query->whereIn('status', ['installed', 'active', 'inactive']);
    }

    public function scopeDiscovered(Builder $query): Builder
    {
        return $query->where('status', 'discovered');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInstalled(): bool
    {
        return in_array($this->status, ['installed', 'active', 'inactive'], true);
    }
}
