<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $query
 * @property string $query_normalized
 * @property string $tier
 * @property int $results_count
 * @property string|null $clicked_content_id
 * @property int|null $click_position
 * @property int $response_time_ms
 * @property string|null $session_id
 * @property string|null $locale
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property-read Space $space
 */
class SearchAnalytic extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'search_analytics';

    protected $fillable = [
        'space_id',
        'query',
        'query_normalized',
        'tier',
        'results_count',
        'clicked_content_id',
        'click_position',
        'response_time_ms',
        'session_id',
        'locale',
        'user_agent',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'results_count' => 'integer',
        'click_position' => 'integer',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
