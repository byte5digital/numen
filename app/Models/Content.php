<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property string $space_id
 * @property string $content_type_id
 * @property string|null $current_version_id
 * @property string|null $draft_version_id
 * @property string $slug
 * @property string $status
 * @property string $locale
 * @property string|null $canonical_id
 * @property array|null $taxonomy
 * @property array|null $metadata
 * @property string|null $hero_image_id
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $refresh_at
 * @property \Carbon\Carbon|null $scheduled_publish_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Space $space
 * @property-read ContentType|null $contentType
 * @property-read ContentVersion|null $currentVersion
 * @property-read ContentVersion|null $draftVersion
 * @property-read ContentDraft|null $autosaveDraft
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentVersion> $versions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ScheduledPublish> $scheduledPublishes
 * @property-read ScheduledPublish|null $nextScheduledPublish
 * @property-read MediaAsset|null $heroImage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MediaAsset> $mediaAssets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineRun> $pipelineRuns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaxonomyTerm> $taxonomyTerms
 */
class Content extends Model
{
    use HasFactory, HasUlids, Searchable, SoftDeletes;

    protected $table = 'contents';

    protected $fillable = [
        'space_id', 'content_type_id', 'current_version_id', 'draft_version_id',
        'slug', 'status', 'locale', 'canonical_id',
        'taxonomy', 'metadata', 'hero_image_id',
        'published_at', 'expires_at', 'refresh_at', 'scheduled_publish_at',
    ];

    protected $casts = [
        'taxonomy' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'refresh_at' => 'datetime',
        'scheduled_publish_at' => 'datetime',
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

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'draft_version_id');
    }

    /** @return HasOne<ContentDraft, $this> */
    public function autosaveDraft(): HasOne
    {
        return $this->hasOne(ContentDraft::class);
    }

    /** @return HasMany<ScheduledPublish, $this> */
    public function scheduledPublishes(): HasMany
    {
        return $this->hasMany(ScheduledPublish::class);
    }

    /** @return HasOne<ScheduledPublish, $this> */
    public function nextScheduledPublish(): HasOne
    {
        return $this->hasOne(ScheduledPublish::class)
            ->where('status', 'pending')
            ->orderBy('publish_at');
    }

    /** @return HasMany<ContentVersion, $this> */
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

    public function pipelineRuns(): HasMany
    {
        return $this->hasMany(PipelineRun::class);
    }

    /** @return BelongsToMany<TaxonomyTerm, $this> */
    public function taxonomyTerms(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyTerm::class, 'content_taxonomy', 'content_id', 'term_id')
            ->withPivot('sort_order', 'auto_assigned', 'confidence')
            ->withTimestamps();
    }

    /** @return BelongsToMany<TaxonomyTerm, $this> */
    public function termsInVocabulary(string $vocabularySlug): BelongsToMany
    {
        return $this->taxonomyTerms()
            ->whereHas('vocabulary', fn (Builder $q) => $q->where('slug', $vocabularySlug));
    }

    /**
     * @param  Builder<Content>  $query
     */
    public function scopeInTerm(Builder $query, string $termId): Builder
    {
        return $query->whereHas('taxonomyTerms', fn (Builder $q) => $q->where('taxonomy_terms.id', $termId));
    }

    /**
     * @param  Builder<Content>  $query
     */
    public function scopeInTaxonomy(Builder $query, string $vocabSlug, string $termSlug): Builder
    {
        return $query->whereHas('taxonomyTerms', function (Builder $q) use ($vocabSlug, $termSlug): void {
            $q->where('taxonomy_terms.slug', $termSlug)
                ->whereHas('vocabulary', fn (Builder $v) => $v->where('slug', $vocabSlug));
        });
    }

    // --- Search (Laravel Scout / Meilisearch) ---

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $version = $this->currentVersion;

        if (! $version) {
            return [];
        }

        return [
            'id' => $this->id,
            'title' => $version->title,
            'excerpt' => $version->excerpt,
            'body' => strip_tags((string) $version->body),
            'blocks_text' => $this->getBlocksPlainText(),
            'seo_title' => is_array($version->seo_data) ? ($version->seo_data['title'] ?? null) : null,
            'seo_description' => is_array($version->seo_data) ? ($version->seo_data['description'] ?? null) : null,
            'content_type' => $this->contentType?->slug,
            'content_type_name' => $this->contentType?->name,
            'space_id' => $this->space_id,
            'locale' => $this->locale,
            'status' => $this->status,
            'slug' => $this->slug,
            'published_at' => $this->published_at !== null ? $this->published_at->timestamp : null,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    private function getBlocksPlainText(): string
    {
        $version = $this->currentVersion;

        if (! $version) {
            return '';
        }

        return $version->blocks()
            ->get()
            ->map(function (ContentBlock $block): string {
                $data = $block->data;

                if (is_string($data)) {
                    return strip_tags($data);
                }

                if (is_array($data)) {
                    $parts = [];
                    foreach (['text', 'body', 'content', 'caption'] as $key) {
                        if (! empty($data[$key]) && is_string($data[$key])) {
                            $parts[] = strip_tags($data[$key]);
                        }
                    }

                    return implode(' ', $parts);
                }

                return '';
            })
            ->filter()
            ->implode(' ');
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
