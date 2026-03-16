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
 * @property string $source_id
 * @property string|null $source_hash
 * @property string|null $numen_content_id
 * @property array|null $numen_media_ids
 * @property string $status
 * @property string|null $error_message
 * @property int $attempt
 * @property string|null $source_payload
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read MigrationSession $session
 */
class MigrationItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'migration_items';

    protected $fillable = [
        'migration_session_id',
        'space_id',
        'source_type_key',
        'source_id',
        'source_hash',
        'numen_content_id',
        'numen_media_ids',
        'status',
        'error_message',
        'attempt',
        'source_payload',
    ];

    protected $casts = [
        'numen_media_ids' => 'array',
        'attempt' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MigrationSession::class, 'migration_session_id');
    }
}
