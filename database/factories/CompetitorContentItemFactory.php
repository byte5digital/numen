<?php

namespace Database\Factories;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitorContentItemFactory extends Factory
{
    protected $model = CompetitorContentItem::class;

    public function definition(): array
    {
        return [
            'source_id' => CompetitorSource::factory(),
            'external_url' => $this->faker->unique()->url(),
            'title' => $this->faker->sentence(),
            'excerpt' => $this->faker->paragraph(),
            'body' => $this->faker->paragraphs(3, true),
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'crawled_at' => now(),
            'content_hash' => md5($this->faker->text()),
            'metadata' => null,
        ];
    }
}
