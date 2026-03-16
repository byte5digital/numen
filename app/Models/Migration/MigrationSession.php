<?php

namespace App\Models\Migration;

use App\Models\Space;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $space_id
 * @property string $created_by
 * @property string $name
 * @property string $source_cms
 * @property string $source_url
 * @property string|null $source_version
 * @property string|null $credentials
 * @property string $status
 * @property int $total_items
 * @property int $processed_items
 * @property int $failed_items
 * @property int $skipped_items
 * @property array|null $options
 * @property string|null $error_message
 * @property array|null $schema_snapshot
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MigrationTypeMapping> $typeMappings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MigrationItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MigrationCheckpoint> $checkpoints
 */
class MigrationSession extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'migration_sessions';

    protected $fillable = [
        'space_id',
        'created_by',
        'name',
        'source_cms',
        'source_url',
        'source_version',
        'credentials',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'skipped_items',
        'options',
        'error_message',
        'schema_snapshot',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted',
        'options' => 'array',
        'schema_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'failed_items' => 'integer',
        'skipped_items' => 'integer',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function typeMappings(): HasMany
    {
        return $this->hasMany(MigrationTypeMapping::class, 'migration_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MigrationItem::class, 'migration_session_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(MigrationCheckpoint::class, 'migration_session_id');
    }
}
