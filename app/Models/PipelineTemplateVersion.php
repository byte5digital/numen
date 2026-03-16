<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $template_id
 * @property string $version
 * @property array $definition
 * @property string|null $changelog
 * @property bool $is_latest
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PipelineTemplate $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineTemplateInstall> $installs
 */
class PipelineTemplateVersion extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'template_id',
        'version',
        'definition',
        'changelog',
        'is_latest',
        'published_at',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_latest' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PipelineTemplate::class, 'template_id');
    }

    public function installs(): HasMany
    {
        return $this->hasMany(PipelineTemplateInstall::class, 'version_id');
    }
}
