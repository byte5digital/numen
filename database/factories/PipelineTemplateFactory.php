<?php

namespace Database\Factories;

use App\Models\PipelineTemplate;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PipelineTemplateFactory extends Factory
{
    protected $model = PipelineTemplate::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true).' Template';

        return [
            'space_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['content', 'seo', 'social', 'email', 'ecommerce']),
            'icon' => $this->faker->randomElement(['document', 'sparkles', 'megaphone', 'mail', 'shopping-cart']),
            'schema_version' => '1.0',
            'is_published' => false,
            'author_name' => $this->faker->name(),
            'author_url' => $this->faker->url(),
            'downloads_count' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }

    public function global(): static
    {
        return $this->state(['space_id' => null]);
    }

    public function forSpace(Space $space): static
    {
        return $this->state(['space_id' => $space->id]);
    }
}
