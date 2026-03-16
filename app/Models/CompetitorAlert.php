<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $space_id
 * @property string $name
 * @property string $type
 * @property array|null $conditions
 * @property bool $is_active
 * @property array|null $notify_channels
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class CompetitorAlert extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'space_id',
        'name',
        'type',
        'conditions',
        'is_active',
        'notify_channels',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'notify_channels' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(CompetitorAlertEvent::class, 'alert_id');
    }
}
