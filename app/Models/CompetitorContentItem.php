<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property string $id
 * @property string $space_id
 * @property string $source_id
 * @property string $external_url
 * @property-read \App\Models\CompetitorSource|null $source
 * @property-read \App\Models\Space|null $space
 * @property string|null $title
 * @property string|null $excerpt
 * @property string|null $body
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon|null $crawled_at
 * @property string|null $content_hash
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class CompetitorContentItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'space_id',
        'source_id',
        'external_url',
        'title',
        'excerpt',
        'body',
        'published_at',
        'crawled_at',
        'content_hash',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'crawled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<CompetitorSource, covariant self> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CompetitorSource::class, 'source_id');
    }

    /** @return BelongsTo<Space, covariant self> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'space_id');
    }

    public function fingerprint(): MorphOne
    {
        return $this->morphOne(ContentFingerprint::class, 'fingerprintable');
    }

    public function differentiationAnalyses(): HasMany
    {
        return $this->hasMany(DifferentiationAnalysis::class, 'competitor_content_id');
    }

    public function alertEvents(): HasMany
    {
        return $this->hasMany(CompetitorAlertEvent::class, 'competitor_content_id');
    }
}
