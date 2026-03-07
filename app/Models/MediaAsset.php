<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $space_id
 * @property string $filename
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $source
 * @property array|null $ai_metadata
 * @property array|null $variants
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class MediaAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'space_id', 'filename', 'disk', 'path', 'mime_type',
        'size_bytes', 'source', 'ai_metadata', 'variants',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
        'variants' => 'array',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_media')
            ->withPivot('role', 'sort_order');
    }

    /**
     * Disk-aware public URL for this asset.
     * Works for both the local 'public' disk and S3-compatible disks (Laravel Cloud).
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
