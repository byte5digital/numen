<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $pipeline_id
 * @property string|null $content_id
 * @property string|null $content_brief_id
 * @property string $status
 * @property string|null $current_stage
 * @property array|null $stage_results
 * @property array|null $context
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ContentPipeline $pipeline
 * @property-read Content|null $content
 * @property-read ContentBrief|null $brief
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AIGenerationLog> $generationLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentVersion> $versions
 */
class PipelineRun extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'pipeline_id', 'content_id', 'content_brief_id',
        'status', 'current_stage', 'stage_results', 'context',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'stage_results' => 'array',
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(ContentPipeline::class, 'pipeline_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(ContentBrief::class, 'content_brief_id');
    }

    public function generationLogs(): HasMany
    {
        return $this->hasMany(AIGenerationLog::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update brief status
        $this->brief?->update(['status' => 'completed']);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'stage_results' => array_merge($this->stage_results ?? [], [
                'failure_reason' => $reason,
            ]),
        ]);

        // Update brief status
        $this->brief?->update(['status' => 'failed']);
    }

    public function addStageResult(string $stageName, array $result): void
    {
        $results = $this->stage_results ?? [];
        $results[$stageName] = $result;
        $this->update(['stage_results' => $results]);
    }
}
