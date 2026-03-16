<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentRefreshSuggestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentRefreshSuggestion extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_refresh_suggestions';

    protected $fillable = [
        'space_id',
        'content_id',
        'status',
        'trigger_type',
        'performance_context',
        'suggestions',
        'urgency_score',
        'brief_id',
        'triggered_at',
        'acted_on_at',
    ];

    protected $casts = [
        'performance_context' => 'array',
        'suggestions' => 'array',
        'urgency_score' => 'decimal:2',
        'triggered_at' => 'datetime',
        'acted_on_at' => 'datetime',
    ];

    protected static function newFactory(): ContentRefreshSuggestionFactory
    {
        return ContentRefreshSuggestionFactory::new();
    }
}
