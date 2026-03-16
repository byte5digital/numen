<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $fingerprintable_type
 * @property string $fingerprintable_id
 * @property array|null $topics
 * @property array|null $entities
 * @property array|null $keywords
 * @property string|null $embedding_vector
 * @property \Carbon\Carbon|null $fingerprinted_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ContentFingerprint extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'fingerprintable_type',
        'fingerprintable_id',
        'topics',
        'entities',
        'keywords',
        'embedding_vector',
        'fingerprinted_at',
    ];

    protected $casts = [
        'topics' => 'array',
        'entities' => 'array',
        'keywords' => 'array',
        'fingerprinted_at' => 'datetime',
    ];

    public function fingerprintable(): MorphTo
    {
        return $this->morphTo();
    }
}
