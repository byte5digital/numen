<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property array|null $intent
 * @property array|null $actions_taken
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $cost_usd
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ChatConversation $conversation
 */
class ChatMessage extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'intent',
        'actions_taken',
        'input_tokens',
        'output_tokens',
        'cost_usd',
    ];

    protected $casts = [
        'intent' => 'array',
        'actions_taken' => 'array',
        'cost_usd' => 'decimal:6',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
