<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $plugin_id
 * @property string|null $space_id
 * @property string $key
 * @property mixed $value
 * @property bool $is_secret
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Plugin $plugin
 */
class PluginSetting extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'plugin_id',
        'space_id',
        'key',
        'value',
        'is_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'array',
        'is_secret' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
