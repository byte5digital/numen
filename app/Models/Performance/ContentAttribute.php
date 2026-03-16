<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentAttributeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAttribute extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_attributes';

    protected $fillable = [
        'space_id',
        'content_id',
        'content_version_id',
        'persona_id',
        'pipeline_run_id',
        'tone',
        'format_type',
        'word_count',
        'heading_count',
        'image_count',
        'topics',
        'target_keywords',
        'taxonomy_terms',
        'ai_quality_score',
        'generation_model',
        'generation_params',
    ];

    protected $casts = [
        'topics' => 'array',
        'target_keywords' => 'array',
        'taxonomy_terms' => 'array',
        'generation_params' => 'array',
        'ai_quality_score' => 'decimal:2',
        'word_count' => 'integer',
        'heading_count' => 'integer',
        'image_count' => 'integer',
    ];

    protected static function newFactory(): ContentAttributeFactory
    {
        return ContentAttributeFactory::new();
    }
}
