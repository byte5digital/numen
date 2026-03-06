<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
