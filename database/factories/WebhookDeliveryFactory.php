<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_id' => (string) Str::ulid(),
            'event_type' => $this->faker->randomElement(['content.published', 'content.updated', 'pipeline.completed']),
            'payload_hash' => hash('sha256', $this->faker->sentence()),
            'payload' => ['id' => (string) Str::ulid(), 'event' => 'content.published', 'data' => []],
            'attempt_number' => 1,
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'http_status' => 200,
            'response_body' => 'OK',
            'error_message' => null,
            'scheduled_at' => now(),
            'delivered_at' => now(),
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookDelivery::STATUS_FAILED,
            'http_status' => 500,
            'response_body' => 'Internal Server Error',
            'delivered_at' => null,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookDelivery::STATUS_ABANDONED,
            'http_status' => null,
            'error_message' => 'Abandoned after 3 attempts',
            'delivered_at' => null,
        ]);
    }
}
