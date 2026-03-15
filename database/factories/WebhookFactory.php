<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'url' => $this->faker->url(),
            'secret' => Str::random(64),
            'events' => ['content.published'],
            'is_active' => true,
            'retry_policy' => null,
            'headers' => null,
            'batch_mode' => false,
            'batch_timeout' => 5000,
        ];
    }
}
