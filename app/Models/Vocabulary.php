<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $hierarchy
 * @property bool $allow_multiple
 * @property array|null $settings
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaxonomyTerm> $terms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaxonomyTerm> $rootTerms
 */
class Vocabulary extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'space_id', 'name', 'slug', 'description',
        'hierarchy', 'allow_multiple', 'settings', 'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'hierarchy' => 'boolean',
        'allow_multiple' => 'boolean',
        'settings' => 'array',
    ];

    // --- Scopes ---

    /**
     * @param  Builder<Vocabulary>  $query
     */
    public function scopeForSpace(Builder $query, string $spaceId): Builder
    {
        return $query->where('space_id', $spaceId);
    }

    /**
     * @param  Builder<Vocabulary>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // --- Relations ---

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return HasMany<TaxonomyTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }

    /** @return HasMany<TaxonomyTerm, $this> */
    public function rootTerms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class)->whereNull('parent_id')->orderBy('sort_order');
    }
}
