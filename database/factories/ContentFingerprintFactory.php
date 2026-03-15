<?php

namespace Database\Factories;

use App\Models\CompetitorContentItem;
use App\Models\ContentFingerprint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentFingerprintFactory extends Factory
{
    protected $model = ContentFingerprint::class;

    public function definition(): array
    {
        return [
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => CompetitorContentItem::factory(),
            'topics' => $this->faker->words(5),
            'entities' => $this->faker->words(3),
            'keywords' => $this->faker->words(8),
            'embedding_vector' => null,
            'fingerprinted_at' => now(),
        ];
    }
}
