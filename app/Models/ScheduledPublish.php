<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $content_id
 * @property string $version_id
 * @property string $scheduled_by
 * @property \Carbon\Carbon $publish_at
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Content $content
 * @property-read ContentVersion $version
 * @property-read User $scheduler
 */
class ScheduledPublish extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_id', 'version_id', 'scheduled_by',
        'publish_at', 'status', 'notes',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
    ];

    // --- Scopes ---

    /**
     * @param  Builder<ScheduledPublish>  $q
     * @return Builder<ScheduledPublish>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    /**
     * @param  Builder<ScheduledPublish>  $q
     * @return Builder<ScheduledPublish>
     */
    public function scopeDue(Builder $q): Builder
    {
        return $q->pending()->where('publish_at', '<=', now());
    }

    // --- Relations ---

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'version_id');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }
}
