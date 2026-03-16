<?php

namespace App\Models\Performance;

use Database\Factories\Performance\ContentAbVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAbVariant extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_ab_variants';

    protected $fillable = [
        'test_id',
        'content_id',
        'label',
        'is_control',
        'generation_params',
        'composite_score',
        'view_count',
        'conversion_rate',
    ];

    protected $casts = [
        'is_control' => 'boolean',
        'generation_params' => 'array',
        'composite_score' => 'decimal:2',
        'view_count' => 'integer',
        'conversion_rate' => 'decimal:4',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(ContentAbTest::class, 'test_id');
    }

    protected static function newFactory(): ContentAbVariantFactory
    {
        return ContentAbVariantFactory::new();
    }
}
