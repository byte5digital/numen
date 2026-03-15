<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'context' => null,
            'pending_action' => null,
            'last_active_at' => now(),
        ];
    }

    public function withPendingAction(array $action = []): static
    {
        return $this->state([
            'pending_action' => $action ?: [
                'action' => 'content.delete',
                'params' => ['content_id' => 'some-id'],
                'message' => 'Are you sure you want to delete this content?',
            ],
        ]);
    }

    public function withSummary(string $summary = 'Summary of past conversation.'): static
    {
        return $this->state([
            'context' => ['summary' => $summary, 'summary_covers_up_to' => 'msg-01'],
        ]);
    }
}
