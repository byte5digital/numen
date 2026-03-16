<?php

namespace App\Models\Performance;

use Database\Factories\Performance\SpacePerformanceModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpacePerformanceModel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'space_performance_models';

    protected $fillable = [
        'space_id',
        'attribute_weights',
        'top_performers',
        'bottom_performers',
        'topic_scores',
        'persona_scores',
        'sample_size',
        'model_confidence',
        'model_version',
        'computed_at',
    ];

    protected $casts = [
        'attribute_weights' => 'array',
        'top_performers' => 'array',
        'bottom_performers' => 'array',
        'topic_scores' => 'array',
        'persona_scores' => 'array',
        'sample_size' => 'integer',
        'model_confidence' => 'decimal:4',
        'computed_at' => 'datetime',
    ];

    protected static function newFactory(): SpacePerformanceModelFactory
    {
        return SpacePerformanceModelFactory::new();
    }
}
