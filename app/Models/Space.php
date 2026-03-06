<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'settings', 'api_config'];

    protected $casts = [
        'settings' => 'array',
        'api_config' => 'array',
    ];

    public function contentTypes(): HasMany
    {
        return $this->hasMany(ContentType::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(ContentPipeline::class);
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(ContentBrief::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }
}
