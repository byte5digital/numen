<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'contents';

    protected $fillable = [
        'space_id', 'content_type_id', 'current_version_id',
        'slug', 'status', 'locale', 'canonical_id',
        'taxonomy', 'metadata', 'hero_image_id',
        'published_at', 'expires_at', 'refresh_at',
    ];

    protected $casts = [
        'taxonomy' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'refresh_at' => 'datetime',
    ];

    // --- Scopes ---

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    public function scopeOfType($query, string $typeSlug)
    {
        return $query->whereHas('contentType', fn ($q) => $q->where('slug', $typeSlug));
    }

    // --- Relations ---

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class)->orderByDesc('version_number');
    }

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_image_id');
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'content_media')
            ->withPivot('role', 'sort_order')
            ->orderByPivot('sort_order');
    }

    // --- Helpers ---

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'refresh_at' => now()->addDays((int) config('numen.pipeline.content_refresh_days', 30)),
        ]);
    }
}
