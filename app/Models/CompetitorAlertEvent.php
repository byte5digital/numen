<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $alert_id
 * @property string $competitor_content_id
 * @property array|null $trigger_data
 * @property \Carbon\Carbon|null $notified_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class CompetitorAlertEvent extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'alert_id',
        'competitor_content_id',
        'trigger_data',
        'notified_at',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'notified_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(CompetitorAlert::class, 'alert_id');
    }

    public function competitorContent(): BelongsTo
    {
        return $this->belongsTo(CompetitorContentItem::class, 'competitor_content_id');
    }
}
