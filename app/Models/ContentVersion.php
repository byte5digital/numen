<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $content_id
 * @property int $version_number
 * @property string|null $label
 * @property string $status
 * @property string|null $parent_version_id
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string $body_format
 * @property array|null $structured_fields
 * @property array|null $seo_data
 * @property string|null $meta_description
 * @property string $author_type
 * @property string $author_id
 * @property string|null $change_reason
 * @property string|null $pipeline_run_id
 * @property array|null $ai_metadata
 * @property string|null $quality_score
 * @property string|null $seo_score
 * @property \Carbon\Carbon|null $scheduled_at
 * @property string|null $content_hash
 * @property string|null $locked_by
 * @property \Carbon\Carbon|null $locked_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Content $content
 * @property-read PipelineRun|null $pipelineRun
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentBlock> $blocks
 * @property-read ContentVersion|null $parentVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentVersion> $childVersions
 */
class ContentVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'version_number',
        'label', 'status', 'parent_version_id',
        'title', 'excerpt', 'body', 'body_format',
        'structured_fields', 'seo_data',
        'author_type', 'author_id', 'change_reason',
        'pipeline_run_id', 'ai_metadata',
        'quality_score', 'seo_score',
        'scheduled_at', 'content_hash',
        'locked_by', 'locked_at',
    ];

    protected $casts = [
        'structured_fields' => 'array',
        'seo_data' => 'array',
        'ai_metadata' => 'array',
        'quality_score' => 'decimal:2',
        'seo_score' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    // --- Relations ---

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    /** @return HasMany<ContentBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('sort_order');
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    /** @return HasMany<ContentVersion, $this> */
    public function childVersions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_version_id');
    }

    // --- Scopes ---

    /** @param Builder<ContentVersion> $q */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** @param Builder<ContentVersion> $q */
    public function scopeDrafts(Builder $q): Builder
    {
        return $q->where('status', 'draft');
    }

    /** @param Builder<ContentVersion> $q */
    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'scheduled');
    }

    /** @param Builder<ContentVersion> $q */
    public function scopeLabeled(Builder $q): Builder
    {
        return $q->whereNotNull('label');
    }

    // --- Helpers ---

    public function hasBlocks(): bool
    {
        return $this->blocks()->exists();
    }

    public function isAiGenerated(): bool
    {
        return $this->author_type === 'ai_agent';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Compute a deterministic hash of the version's content for fast equality checks.
     */
    public function computeHash(): string
    {
        $payload = json_encode([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'structured_fields' => $this->structured_fields,
            'seo_data' => $this->seo_data,
        ]);

        return hash('sha256', (string) $payload);
    }

    /**
     * Get meta_description from seo_data array.
     */
    public function getMetaDescriptionAttribute(): ?string
    {
        return isset($this->seo_data['meta_description']) ? (string) $this->seo_data['meta_description'] : null;
    }
}
