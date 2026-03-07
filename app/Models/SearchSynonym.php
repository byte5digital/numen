<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $term
 * @property array<int, string> $synonyms
 * @property bool $is_one_way
 * @property string $source
 * @property bool $approved
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 */
class SearchSynonym extends Model
{
    use HasUlids;

    protected $table = 'search_synonyms';

    protected $fillable = [
        'space_id',
        'term',
        'synonyms',
        'is_one_way',
        'source',
        'approved',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'synonyms' => 'array',
        'is_one_way' => 'boolean',
        'approved' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
