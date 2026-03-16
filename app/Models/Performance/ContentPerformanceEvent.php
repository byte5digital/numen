<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentPerformanceEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPerformanceEvent extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_performance_events';

    protected $fillable = [
        'space_id',
        'content_id',
        'content_version_id',
        'variant_id',
        'event_type',
        'source',
        'value',
        'metadata',
        'session_id',
        'visitor_id',
        'occurred_at',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function newFactory(): ContentPerformanceEventFactory
    {
        return ContentPerformanceEventFactory::new();
    }
}
