<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable delivery log — records each attempt to deliver a webhook event.
 *
 * @property string $id
 * @property string $webhook_id
 * @property string $event_id
 * @property string $event_type
 * @property string|null $payload_hash
 * @property int $attempt_number
 * @property string $status
 * @property int|null $http_status
 * @property string|null $response_body
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon $created_at
 * @property-read Webhook $webhook
 */
class WebhookDelivery extends Model
{
    use HasUlids;

    // Delivery status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    /** Immutable log — no updated_at column. */
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'event_id',
        'event_type',
        'payload_hash',
        'attempt_number',
        'status',
        'http_status',
        'response_body',
        'error_message',
        'scheduled_at',
        'delivered_at',
        'created_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'http_status' => 'integer',
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
