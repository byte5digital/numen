<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentAbTestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentAbTest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_ab_tests';

    protected $fillable = [
        'space_id',
        'name',
        'hypothesis',
        'status',
        'metric',
        'traffic_split',
        'min_sample_size',
        'significance_threshold',
        'started_at',
        'ended_at',
        'winner_variant_id',
        'conclusion',
    ];

    protected $casts = [
        'traffic_split' => 'decimal:4',
        'min_sample_size' => 'integer',
        'significance_threshold' => 'decimal:4',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'conclusion' => 'array',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ContentAbVariant::class, 'test_id');
    }

    protected static function newFactory(): ContentAbTestFactory
    {
        return ContentAbTestFactory::new();
    }
}
