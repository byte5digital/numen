<?php

namespace Database\Factories;

use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaxonomyTermFactory extends Factory
{
    protected $model = TaxonomyTerm::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'vocabulary_id' => Vocabulary::factory(),
            'parent_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->optional()->sentence(),
            'path' => null, // computed in booted()
            'depth' => 0,
            'sort_order' => 0,
            'metadata' => null,
            'content_count' => 0,
        ];
    }

    public function withParent(TaxonomyTerm $parent): static
    {
        return $this->state([
            'vocabulary_id' => $parent->vocabulary_id,
            'parent_id' => $parent->id,
        ])->afterCreating(function (TaxonomyTerm $term) use ($parent): void {
            // path is computed via booted(), but parent relation may not be loaded
            $term->setRelation('parent', $parent);
            $term->computePath();
            $term->saveQuietly();
        });
    }
}
