<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $source_id
 * @property string $target_id
 * @property string $edge_type
 * @property float $weight
 * @property array<string, mixed> $edge_metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ContentGraphNode $sourceNode
 * @property-read ContentGraphNode $targetNode
 */
class ContentGraphEdge extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'content_graph_edges';

    protected $fillable = [
        'id',
        'space_id',
        'source_id',
        'target_id',
        'edge_type',
        'weight',
        'edge_metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'weight' => 'float',
        'edge_metadata' => 'array',
    ];

    /** @var array<string, string> */
    protected $attributes = [
        'edge_metadata' => '{}',
    ];

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(ContentGraphNode::class, 'source_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(ContentGraphNode::class, 'target_id');
    }
}
