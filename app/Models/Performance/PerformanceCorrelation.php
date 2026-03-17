<?php

namespace App\Models\Performance;

use Database\Factories\Performance\PerformanceCorrelationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceCorrelation extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'performance_correlations';

    protected $fillable = [
        'space_id',
        'content_id',
        'attribute_name',
        'metric_name',
        'correlation_coefficient',
        'p_value',
        'sample_size',
        'insight',
        'metadata',
    ];

    protected $casts = [
        'correlation_coefficient' => 'decimal:4',
        'p_value' => 'decimal:4',
        'sample_size' => 'integer',
        'metadata' => 'array',
    ];

    protected static function newFactory(): PerformanceCorrelationFactory
    {
        return PerformanceCorrelationFactory::new();
    }
}
