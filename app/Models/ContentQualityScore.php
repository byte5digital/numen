<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $space_id
 * @property string $content_id
 * @property string|null $content_version_id
 * @property float $overall_score
 * @property float|null $readability_score
 * @property float|null $seo_score
 * @property float|null $brand_score
 * @property float|null $factual_score
 * @property float|null $engagement_score
 * @property string|null $scoring_model
 * @property int|null $scoring_duration_ms
 * @property \Carbon\Carbon $scored_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read Content $content
 * @property-read ContentVersion|null $contentVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentQualityScoreItem> $items
 */
class ContentQualityScore extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'space_id',
        'content_id',
        'content_version_id',
        'overall_score',
        'readability_score',
        'seo_score',
        'brand_score',
        'factual_score',
        'engagement_score',
        'scoring_model',
        'scoring_duration_ms',
        'scored_at',
    ];

    protected $casts = [
        'overall_score' => 'float',
        'readability_score' => 'float',
        'seo_score' => 'float',
        'brand_score' => 'float',
        'factual_score' => 'float',
        'engagement_score' => 'float',
        'scoring_duration_ms' => 'integer',
        'scored_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function contentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentQualityScoreItem::class, 'score_id');
    }
}
