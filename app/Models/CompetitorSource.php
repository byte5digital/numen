<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property string $url
 * @property string|null $feed_url
 * @property string $crawler_type
 * @property array|null $config
 * @property bool $is_active
 * @property int $crawl_interval_minutes
 * @property \Carbon\Carbon|null $last_crawled_at
 * @property int $error_count
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class CompetitorSource extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'space_id',
        'name',
        'url',
        'feed_url',
        'crawler_type',
        'config',
        'is_active',
        'crawl_interval_minutes',
        'last_crawled_at',
        'error_count',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'crawl_interval_minutes' => 'integer',
        'last_crawled_at' => 'datetime',
        'error_count' => 'integer',
    ];

    public function contentItems(): HasMany
    {
        return $this->hasMany(CompetitorContentItem::class, 'source_id');
    }
}
