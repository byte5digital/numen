<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property array $dimension_weights
 * @property array $thresholds
 * @property array $enabled_dimensions
 * @property bool $auto_score_on_publish
 * @property bool $pipeline_gate_enabled
 * @property float $pipeline_gate_min_score
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 */
class ContentQualityConfig extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'space_id',
        'dimension_weights',
        'thresholds',
        'enabled_dimensions',
        'auto_score_on_publish',
        'pipeline_gate_enabled',
        'pipeline_gate_min_score',
    ];

    protected $casts = [
        'dimension_weights' => 'array',
        'thresholds' => 'array',
        'enabled_dimensions' => 'array',
        'auto_score_on_publish' => 'boolean',
        'pipeline_gate_enabled' => 'boolean',
        'pipeline_gate_min_score' => 'float',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
