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
 * @property string|null $source_type_label
 * @property string|null $numen_content_type_id
 * @property string|null $numen_type_slug
 * @property array $field_map
 * @property array|null $ai_suggestions
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read MigrationSession $session
 */
class MigrationTypeMapping extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'migration_type_mappings';

    protected $fillable = [
        'migration_session_id',
        'space_id',
        'source_type_key',
        'source_type_label',
        'numen_content_type_id',
        'numen_type_slug',
        'field_map',
        'ai_suggestions',
        'status',
    ];

    protected $casts = [
        'field_map' => 'array',
        'ai_suggestions' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(MigrationSession::class, 'migration_session_id');
    }
}
