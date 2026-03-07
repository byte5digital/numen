<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $vocabulary_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $path
 * @property int $depth
 * @property int $sort_order
 * @property array|null $metadata
 * @property int $content_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Vocabulary $vocabulary
 * @property-read TaxonomyTerm|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaxonomyTerm> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaxonomyTerm> $childrenRecursive
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 */
class TaxonomyTerm extends Model
{
    use HasUlids;

    protected $fillable = [
        'vocabulary_id', 'parent_id', 'name', 'slug',
        'description', 'path', 'depth', 'sort_order',
        'metadata', 'content_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'content_count' => 'integer',
    ];

    // --- Boot ---

    protected static function booted(): void
    {
        static::creating(function (TaxonomyTerm $term): void {
            if (empty($term->slug)) {
                $term->slug = Str::slug($term->name);
            }
            $term->computePath();
        });

        static::updating(function (TaxonomyTerm $term): void {
            if ($term->isDirty('parent_id')) {
                $term->computePath();
            }
        });
    }

    // --- Scopes ---

    /**
     * @param  Builder<TaxonomyTerm>  $query
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<TaxonomyTerm>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<TaxonomyTerm>  $query
     */
    public function scopeInVocabulary(Builder $query, string $vocabularyId): Builder
    {
        return $query->where('vocabulary_id', $vocabularyId);
    }

    /**
     * @param  Builder<TaxonomyTerm>  $query
     */
    public function scopeDescendantsOf(Builder $query, string $termId): Builder
    {
        return $query->where('path', 'like', "%/{$termId}/%");
    }

    // --- Relations ---

    /** @return BelongsTo<Vocabulary, $this> */
    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class);
    }

    /** @return BelongsTo<TaxonomyTerm, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'parent_id');
    }

    /** @return HasMany<TaxonomyTerm, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsToMany<Content, $this> */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_taxonomy', 'term_id', 'content_id')
            ->withPivot('sort_order', 'auto_assigned', 'confidence')
            ->withTimestamps();
    }

    /** Recursive eager-load for tree building. @return HasMany<TaxonomyTerm, $this> */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    // --- Helpers ---

    public function computePath(): void
    {
        if ($this->parent_id && $this->parent) {
            $this->path = $this->parent->path.'/'.$this->id;
            $this->depth = $this->parent->depth + 1;
        } else {
            $this->path = '/'.($this->id ?? '');
            $this->depth = 0;
        }
    }

    /** @return array<int, string> */
    public function getAncestorIds(): array
    {
        return array_values(array_filter(explode('/', $this->path ?? '')));
    }

    public function isAncestorOf(TaxonomyTerm $other): bool
    {
        return str_contains($other->path ?? '', '/'.$this->id.'/');
    }

    public function incrementContentCount(): void
    {
        $this->increment('content_count');
    }

    public function decrementContentCount(): void
    {
        $this->decrement('content_count');
    }
}
