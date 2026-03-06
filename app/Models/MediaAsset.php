<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'space_id', 'filename', 'disk', 'path', 'mime_type',
        'size_bytes', 'source', 'ai_metadata', 'variants',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
        'variants'    => 'array',
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
}
