<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentAttribute> */
class ContentAttributeFactory extends Factory
{
    protected $model = ContentAttribute::class;

    public function definition(): array
    {
        return [
            'space_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'content_version_id' => $this->faker->boolean(70) ? strtoupper(Str::ulid()) : null,
            'persona_id' => $this->faker->boolean(60) ? strtoupper(Str::ulid()) : null,
            'pipeline_run_id' => $this->faker->boolean(80) ? strtoupper(Str::ulid()) : null,
            'tone' => $this->faker->randomElement(['professional', 'casual', 'authoritative', 'friendly']),
            'format_type' => $this->faker->randomElement(['blog_post', 'landing_page', 'product_page', 'guide']),
            'word_count' => $this->faker->numberBetween(300, 3000),
            'heading_count' => $this->faker->numberBetween(2, 15),
            'image_count' => $this->faker->numberBetween(0, 10),
            'topics' => ['seo', 'content marketing', 'ai'],
            'target_keywords' => ['content strategy', 'AI writing', 'SEO tools'],
            'taxonomy_terms' => ['category' => 'marketing', 'tags' => ['ai', 'seo']],
            'ai_quality_score' => $this->faker->randomFloat(2, 50, 100),
            'generation_model' => $this->faker->randomElement(['gpt-4o', 'claude-3-5-sonnet']),
            'generation_params' => ['temperature' => 0.7, 'max_tokens' => 2000],
        ];
    }
}
