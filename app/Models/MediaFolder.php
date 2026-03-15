<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'folder_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForSpace(Builder $query, int $spaceId): Builder
    {
        return $query->where('space_id', $spaceId);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getAssetCountAttribute(): int
    {
        return $this->assets()->count();
    }
}