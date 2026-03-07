<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $content_id
 * @property string $content_version_id
 * @property int $chunk_index
 * @property string $chunk_type
 * @property string $chunk_text
 * @property string $embedding
 * @property string $embedding_model
 * @property int $token_count
 * @property array<string,mixed> $metadata
 * @property string $space_id
 * @property string $locale
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Content $content
 * @property-read ContentVersion $contentVersion
 */
class ContentEmbedding extends Model
{
    use HasUlids;

    protected $table = 'content_embeddings';

    protected $fillable = [
        'id',
        'content_id',
        'content_version_id',
        'chunk_index',
        'chunk_type',
        'chunk_text',
        'embedding',
        'embedding_model',
        'token_count',
        'metadata',
        'space_id',
        'locale',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'chunk_index' => 'integer',
        'token_count' => 'integer',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function contentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class);
    }
}
