<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit trail. No update or delete operations are exposed.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $space_id
 * @property string $action
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property array|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property-read User|null $user
 * @property-read Space|null $space
 * @property-read Model|null $resource
 */
class AuditLog extends Model
{
    use HasUlids;

    /** @var bool Audit logs are append-only — never update rows. */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'space_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Immutability guards ───────────────────────────────────────────────

    /**
     * Prevent updates to audit log records.
     */
    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            $this->created_at = now();

            return parent::save($options);
        }

        throw new \LogicException('AuditLog records are immutable and cannot be updated.');
    }

    /**
     * Prevent deletion of audit log records from application code.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('AuditLog records are immutable and cannot be deleted. Use the prune command for retention policy.');
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Polymorphic resource relationship.
     * Usage: $log->resource → App\Models\Content, Role, etc.
     */
    public function resource(): MorphTo
    {
        return $this->morphTo('resource', 'resource_type', 'resource_id');
    }
}
