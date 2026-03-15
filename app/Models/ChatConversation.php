<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $space_id
 * @property int $user_id
 * @property string|null $title
 * @property array|null $context
 * @property array|null $pending_action
 * @property \Carbon\Carbon|null $last_active_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Space $space
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $messages
 */
class ChatConversation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'space_id',
        'user_id',
        'title',
        'context',
        'pending_action',
        'last_active_at',
    ];

    protected $casts = [
        'context' => 'array',
        'pending_action' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
