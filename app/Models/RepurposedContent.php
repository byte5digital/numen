<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $space_id
 * @property int $source_content_id
 * @property int|null $format_template_id
 * @property int|null $batch_id
 * @property string $format_key
 * @property string $status
 * @property string|null $output
 * @property array|null $output_parts
 * @property int|null $tokens_used
 * @property int|null $persona_id
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $repurposed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read Content $sourceContent
 * @property-read FormatTemplate|null $formatTemplate
 * @property-read Persona|null $persona
 * @property-read RepurposingBatch|null $batch
 * @property-read bool $is_stale
 */
class RepurposedContent extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'space_id',
        'source_content_id',
        'format_template_id',
        'batch_id',
        'format_key',
        'status',
        'output',
        'output_parts',
        'tokens_used',
        'persona_id',
        'error_message',
        'repurposed_at',
    ];

    protected $casts = [
        'output_parts' => 'array',
        'repurposed_at' => 'datetime',
        'tokens_used' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function sourceContent(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'source_content_id');
    }

    public function formatTemplate(): BelongsTo
    {
        return $this->belongsTo(FormatTemplate::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return BelongsTo<RepurposingBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(RepurposingBatch::class, 'batch_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForFormat(Builder $query, string $key): Builder
    {
        return $query->where('format_key', $key);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Returns true if the source content has been updated after last repurposing.
     */
    public function getIsStaleAttribute(): bool
    {
        if ($this->repurposed_at === null) {
            return true;
        }

        $sourceUpdated = $this->sourceContent->updated_at;

        if ($sourceUpdated === null) {
            return false;
        }

        return $this->repurposed_at->lt($sourceUpdated);
    }
}
