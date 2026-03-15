<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $score_id
 * @property string $dimension
 * @property string $category
 * @property string $rule_key
 * @property string $label
 * @property string $severity
 * @property float $score_impact
 * @property string $message
 * @property string|null $suggestion
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ContentQualityScore $score
 */
class ContentQualityScoreItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'score_id',
        'dimension',
        'category',
        'rule_key',
        'label',
        'severity',
        'score_impact',
        'message',
        'suggestion',
        'metadata',
    ];

    protected $casts = [
        'score_impact' => 'float',
        'metadata' => 'array',
    ];

    public function score(): BelongsTo
    {
        return $this->belongsTo(ContentQualityScore::class, 'score_id');
    }
}
