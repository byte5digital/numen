<?php

declare(strict_types=1);

namespace App\Models\Performance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPerformanceEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'space_id',
        'content_id',
        'event_type',
        'source',
        'value',
        'metadata',
        'session_id',
        'visitor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
