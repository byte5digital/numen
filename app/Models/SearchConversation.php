<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $space_id
 * @property string $session_id
 * @property array<int, array<string, mixed>> $messages
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $expires_at
 * @property-read Space $space
 */
class SearchConversation extends Model
{
    use HasUlids;

    protected $table = 'search_conversations';

    protected $fillable = [
        'space_id',
        'session_id',
        'messages',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'messages' => 'array',
        'expires_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Add a message to the conversation.
     *
     * @param  array<string, mixed>  $message
     */
    public function addMessage(array $message): void
    {
        $messages = $this->messages ?? [];
        $messages[] = array_merge($message, ['created_at' => now()->toISOString()]);
        $this->messages = $messages;
        $this->updated_at = now();
        $this->save();
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
