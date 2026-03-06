<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property string $slug
 * @property array $schema
 * @property array|null $generation_config
 * @property array|null $seo_config
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 */
class ContentType extends Model
{
    use HasUlids;

    protected $fillable = ['space_id', 'name', 'slug', 'schema', 'generation_config', 'seo_config'];

    protected $casts = [
        'schema' => 'array',
        'generation_config' => 'array',
        'seo_config' => 'array',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}
