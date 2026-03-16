<?php

namespace App\Models\Migration;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $migration_session_id
 * @property string $space_id
 * @property string $source_type_key
 * @property string $last_cursor
 * @property \Carbon\Carbon|null $last_synced_at
 * @property int $item_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read MigrationSession $session
 */
class MigrationCheckpoint extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'migration_checkpoints';

    protected $fillable = [
        'migration_session_id',
        'space_id',
        'source_type_key',
        'last_cursor',
        'last_synced_at',
        'item_count',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'item_count' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MigrationSession::class, 'migration_session_id');
    }
}
