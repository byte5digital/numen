<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property array $stages
 * @property array|null $trigger_config
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PipelineRun> $runs
 */
class ContentPipeline extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['space_id', 'name', 'stages', 'trigger_config', 'is_active'];

    protected $casts = [
        'stages' => 'array',
        'trigger_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class, 'pipeline_id');
    }

    public function getStageByName(string $name): ?array
    {
        return collect($this->stages)->firstWhere('name', $name);
    }

    public function getStageAfter(string $currentStageName): ?array
    {
        $stages = collect($this->stages);
        $currentIndex = $stages->search(fn ($s) => $s['name'] === $currentStageName);

        if ($currentIndex === false || $currentIndex >= $stages->count() - 1) {
            return null;
        }

        return $stages[$currentIndex + 1];
    }
}
