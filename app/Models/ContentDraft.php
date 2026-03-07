<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $content_id
 * @property int $user_id
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string $body_format
 * @property array|null $structured_fields
 * @property array|null $seo_data
 * @property array|null $blocks_snapshot
 * @property string|null $base_version_id
 * @property \Carbon\Carbon $last_saved_at
 * @property int $save_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Content $content
 * @property-read User $user
 * @property-read ContentVersion|null $baseVersion
 */
class ContentDraft extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'user_id',
        'title', 'excerpt', 'body', 'body_format',
        'structured_fields', 'seo_data', 'blocks_snapshot',
        'base_version_id',
        'last_saved_at', 'save_count',
    ];

    protected $casts = [
        'structured_fields' => 'array',
        'seo_data' => 'array',
        'blocks_snapshot' => 'array',
        'last_saved_at' => 'datetime',
        'save_count' => 'integer',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'base_version_id');
    }
}
