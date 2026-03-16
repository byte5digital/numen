<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentPerformanceSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPerformanceSnapshot extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_performance_snapshots';

    protected $fillable = [
        'space_id',
        'content_id',
        'content_version_id',
        'period_type',
        'period_start',
        'views',
        'unique_visitors',
        'avg_time_on_page_s',
        'bounce_rate',
        'avg_scroll_depth',
        'engagement_events',
        'conversions',
        'conversion_rate',
        'composite_score',
    ];

    protected $casts = [
        'period_start' => 'date',
        'views' => 'integer',
        'unique_visitors' => 'integer',
        'avg_time_on_page_s' => 'decimal:2',
        'bounce_rate' => 'decimal:4',
        'avg_scroll_depth' => 'decimal:4',
        'engagement_events' => 'integer',
        'conversions' => 'integer',
        'conversion_rate' => 'decimal:4',
        'composite_score' => 'decimal:2',
    ];

    protected static function newFactory(): ContentPerformanceSnapshotFactory
    {
        return ContentPerformanceSnapshotFactory::new();
    }
}
