<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string|null $content_id
 * @property string|null $brief_id
 * @property string $competitor_content_id
 * @property float $similarity_score
 * @property float $differentiation_score
 * @property array|null $angles
 * @property array|null $gaps
 * @property array|null $recommendations
 * @property \Carbon\Carbon|null $analyzed_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class DifferentiationAnalysis extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'space_id',
        'content_id',
        'brief_id',
        'competitor_content_id',
        'similarity_score',
        'differentiation_score',
        'angles',
        'gaps',
        'recommendations',
        'analyzed_at',
    ];

    protected $casts = [
        'similarity_score' => 'float',
        'differentiation_score' => 'float',
        'angles' => 'array',
        'gaps' => 'array',
        'recommendations' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function competitorContent(): BelongsTo
    {
        return $this->belongsTo(CompetitorContentItem::class, 'competitor_content_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(ContentBrief::class, 'brief_id');
    }
}
