<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for the content_taxonomy table.
 *
 * @property bool $auto_assigned
 * @property float|null $confidence
 * @property int $sort_order
 */
class ContentTaxonomyPivot extends Pivot
{
    protected $table = 'content_taxonomy';

    protected $casts = [
        'auto_assigned' => 'boolean',
        'confidence' => 'float',
        'sort_order' => 'integer',
    ];
}
