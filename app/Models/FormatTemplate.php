<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $space_id
 * @property string $format_key
 * @property string $name
 * @property string|null $description
 * @property string $system_prompt
 * @property string $user_prompt_template
 * @property array|null $output_schema
 * @property int $max_tokens
 * @property bool $is_default
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space|null $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RepurposedContent> $repurposedContents
 */
class FormatTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_id',
        'format_key',
        'name',
        'description',
        'system_prompt',
        'user_prompt_template',
        'output_schema',
        'max_tokens',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'output_schema' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'max_tokens' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function repurposedContents(): HasMany
    {
        return $this->hasMany(RepurposedContent::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Return templates that are either space-specific or global (space_id IS NULL).
     */
    public function scopeForSpace(Builder $query, ?int $spaceId): Builder
    {
        return $query->where(function (Builder $q) use ($spaceId) {
            $q->where('space_id', $spaceId)
                ->orWhereNull('space_id');
        });
    }

    public function scopeForFormat(Builder $query, string $formatKey): Builder
    {
        return $query->where('format_key', $formatKey);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the best template for a given space + format.
     * Space-specific templates take priority over global defaults.
     */
    public static function getForSpace(int $spaceId, string $formatKey): ?self
    {
        $templates = static::query()
            ->active()
            ->forFormat($formatKey)
            ->forSpace($spaceId)
            ->orderByRaw('CASE WHEN space_id IS NULL THEN 1 ELSE 0 END')
            ->get();

        // Prefer space-specific first
        return $templates->firstWhere('space_id', $spaceId)
            ?? $templates->firstWhere('space_id', null);
    }
}
