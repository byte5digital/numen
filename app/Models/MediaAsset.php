<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property string|null $alt_text
 * @property string|null $caption
 * @property array|null $tags
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration
 * @property int|null $file_size
 * @property array|null $metadata
 * @property bool $is_public
 * @property int|null $folder_id
 * @property string|null $url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 * @property-read \Illuminate\Database\Eloquent\Relations\Pivot $pivot
 */
class MediaAsset extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'space_id', 'filename', 'disk', 'path', 'mime_type',
        'size_bytes', 'source', 'ai_metadata', 'variants',
        'alt_text', 'caption', 'tags', 'file_size', 'width',
        'height', 'duration', 'metadata', 'is_public', 'folder_id',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
        'variants' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
    ];
}
