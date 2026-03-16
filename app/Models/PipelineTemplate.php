<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $space_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $category
 * @property string|null $icon
 * @property string $schema_version
 * @property bool $is_published
 * @property string|null $author_name
 * @property string|null $author_url
 * @property int $downloads_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Space|null $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineTemplateVersion> $versions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineTemplateVersion>|PipelineTemplateVersion|null $latestVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineTemplateInstall> $installs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineTemplateRating> $ratings
 */
class PipelineTemplate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'space_id',
        'name',
        'slug',
        'description',
        'category',
        'icon',
        'schema_version',
        'author_name',
        'author_url',
        'downloads_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'downloads_count' => 'integer',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PipelineTemplateVersion::class, 'template_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PipelineTemplateVersion::class, 'template_id')->where('is_latest', true);
    }

    public function installs(): HasMany
    {
        return $this->hasMany(PipelineTemplateInstall::class, 'template_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(PipelineTemplateRating::class, 'template_id');
    }

    public function isGlobal(): bool
    {
        return $this->space_id === null;
    }

    public function averageRating(): float
    {
        return (float) $this->ratings()->avg('rating');
    }
}
