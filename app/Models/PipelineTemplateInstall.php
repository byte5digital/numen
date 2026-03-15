<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $template_id
 * @property string $version_id
 * @property string $space_id
 * @property string|null $pipeline_id
 * @property \Carbon\Carbon $installed_at
 * @property array|null $config_overrides
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PipelineTemplate $template
 * @property-read PipelineTemplateVersion $version
 * @property-read Space $space
 */
class PipelineTemplateInstall extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'template_id',
        'version_id',
        'space_id',
        'pipeline_id',
        'installed_at',
        'config_overrides',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'config_overrides' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PipelineTemplate::class, 'template_id');
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(PipelineTemplateVersion::class, 'version_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'space_id');
    }
}
