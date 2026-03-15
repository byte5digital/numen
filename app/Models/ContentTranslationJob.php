<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_id
 * @property string $source_content_id
 * @property string|null $target_content_id
 * @property string $source_locale
 * @property string $target_locale
 * @property string $status
 * @property string|null $persona_id
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space          $space
 * @property-read Content        $sourceContent
 * @property-read Content|null   $targetContent
 * @property-read Persona|null   $persona
 */
class ContentTranslationJob extends Model
{
    protected $table = 'content_translation_jobs';

    protected $fillable = [
        'space_id',
        'source_content_id',
        'target_content_id',
        'source_locale',
        'target_locale',
        'status',
        'persona_id',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function sourceContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'source_content_id');
    }

    public function targetContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'target_content_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
