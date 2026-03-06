<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $content_id
 * @property int $version_number
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string $body_format
 * @property array|null $structured_fields
 * @property array|null $seo_data
 * @property string $author_type
 * @property string $author_id
 * @property string|null $change_reason
 * @property string|null $pipeline_run_id
 * @property array|null $ai_metadata
 * @property string|null $quality_score
 * @property string|null $seo_score
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Content $content
 * @property-read PipelineRun|null $pipelineRun
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentBlock> $blocks
 */
class ContentVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'version_number',
        'title', 'excerpt', 'body', 'body_format',
        'structured_fields', 'seo_data',
        'author_type', 'author_id', 'change_reason',
        'pipeline_run_id', 'ai_metadata',
        'quality_score', 'seo_score',
    ];

    protected $casts = [
        'structured_fields' => 'array',
        'seo_data' => 'array',
        'ai_metadata' => 'array',
        'quality_score' => 'decimal:2',
        'seo_score' => 'decimal:2',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    /** @return HasMany<ContentBlock, ContentVersion> */
    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('sort_order');
    }

    public function hasBlocks(): bool
    {
        return $this->blocks()->exists();
    }

    public function isAiGenerated(): bool
    {
        return $this->author_type === 'ai_agent';
    }
}
