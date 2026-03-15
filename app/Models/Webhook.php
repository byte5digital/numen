<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $id
 * @property string $space_id
 * @property string $url
 * @property string $secret
 * @property array $events
 * @property bool $is_active
 * @property array|null $retry_policy
 * @property array|null $headers
 * @property bool $batch_mode
 * @property int $batch_timeout
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WebhookDelivery> $deliveries
 */
class Webhook extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'space_id',
        'url',
        'secret',
        'events',
        'is_active',
        'retry_policy',
        'headers',
        'batch_mode',
        'batch_timeout',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'retry_policy' => 'array',
        'headers' => 'array',
        'batch_mode' => 'boolean',
        'batch_timeout' => 'integer',
    ];

    /**
     * Decrypt the secret when reading.
     */
    public function getSecretAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Fallback for plaintext values during migration period
            return $value;
        }
    }

    /**
     * Encrypt the secret when writing.
     */
    public function setSecretAttribute(string $value): void
    {
        $this->attributes['secret'] = Crypt::encryptString($value);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Check whether this webhook is subscribed to a given event type.
     *
     * Supports exact matches ("content.published") and wildcard patterns
     * ("content.*", "pipeline.*", "*").
     */
    public function matchesEvent(string $eventType): bool
    {
        foreach ($this->events as $pattern) {
            if ($this->eventPatternMatches($pattern, $eventType)) {
                return true;
            }
        }

        return false;
    }

    private function eventPatternMatches(string $pattern, string $eventType): bool
    {
        // Exact match
        if ($pattern === $eventType) {
            return true;
        }

        // Global wildcard
        if ($pattern === '*') {
            return true;
        }

        // Domain wildcard: e.g. "pipeline.*" matches "pipeline.completed"
        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);

            return str_starts_with($eventType, $prefix.'.');
        }

        return false;
    }
}
