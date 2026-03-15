<?php

namespace Database\Factories;

use App\Models\CompetitorSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompetitorSourceFactory extends Factory
{
    protected $model = CompetitorSource::class;

    public function definition(): array
    {
        return [
            'space_id' => Str::ulid()->toBase32(),
            'name' => $this->faker->company(),
            'url' => $this->faker->url(),
            'feed_url' => $this->faker->optional()->url(),
            'crawler_type' => $this->faker->randomElement(['rss', 'sitemap', 'scrape', 'api']),
            'config' => null,
            'is_active' => true,
            'crawl_interval_minutes' => 60,
            'last_crawled_at' => null,
            'error_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
