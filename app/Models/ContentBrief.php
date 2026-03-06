<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $space_id
 * @property string|null $content_id
 * @property string|null $pipeline_id
 * @property string $title
 * @property string|null $description
 * @property array|null $requirements
 * @property array|null $reference_urls
 * @property array|null $target_keywords
 * @property string $content_type_slug
 * @property string $target_locale
 * @property string|null $persona_id
 * @property string $source
 * @property string $priority
 * @property string $status
 * @property \Carbon\Carbon|null $due_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read Space $space
 * @property-read ContentPipeline|null $pipeline
 * @property-read Persona|null $persona
 * @property-read Content|null $targetContent
 * @property-read PipelineRun|null $pipelineRun
 */
class ContentBrief extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'space_id', 'content_id', 'pipeline_id', 'title', 'description',
        'requirements', 'reference_urls', 'target_keywords',
        'content_type_slug', 'target_locale', 'persona_id',
        'source', 'priority', 'status', 'due_at',
    ];

    protected $casts = [
        'requirements' => 'array',
        'reference_urls' => 'array',
        'target_keywords' => 'array',
        'due_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class, 'pipeline_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function targetContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    public function pipelineRun(): HasOne
    {
        return $this->hasOne(PipelineRun::class);
    }

    public function isUpdate(): bool
    {
        return $this->content_id !== null;
    }
}
