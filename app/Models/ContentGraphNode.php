<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $content_id
 * @property string $space_id
 * @property string $locale
 * @property array<int, string> $entity_labels
 * @property string|null $cluster_id
 * @property array<string, mixed> $node_metadata
 * @property \Carbon\Carbon|null $indexed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Content $content
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentGraphEdge> $outboundEdges
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentGraphEdge> $inboundEdges
 */
class ContentGraphNode extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_graph_nodes';

    protected $fillable = [
        'id',
        'content_id',
        'space_id',
        'locale',
        'entity_labels',
        'cluster_id',
        'node_metadata',
        'indexed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entity_labels' => 'array',
        'node_metadata' => 'array',
        'indexed_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    public function outboundEdges(): HasMany
    {
        return $this->hasMany(ContentGraphEdge::class, 'source_id');
    }

    public function inboundEdges(): HasMany
    {
        return $this->hasMany(ContentGraphEdge::class, 'target_id');
    }
}
