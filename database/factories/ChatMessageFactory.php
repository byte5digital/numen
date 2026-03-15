<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant']),
            'content' => $this->faker->paragraph(),
            'intent' => null,
            'actions_taken' => null,
            'input_tokens' => $this->faker->numberBetween(10, 200),
            'output_tokens' => $this->faker->numberBetween(10, 500),
            'cost_usd' => $this->faker->randomFloat(6, 0.0001, 0.01),
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => 'user', 'input_tokens' => null, 'output_tokens' => null, 'cost_usd' => null]);
    }

    public function assistant(): static
    {
        return $this->state(['role' => 'assistant']);
    }

    public function withIntent(string $action = 'content.query'): static
    {
        return $this->state([
            'intent' => [
                'action' => $action,
                'entity' => 'content',
                'params' => [],
                'confidence' => 0.9,
                'requires_confirmation' => false,
            ],
        ]);
    }
}
