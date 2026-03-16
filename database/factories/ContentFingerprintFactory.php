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
        $words = $this->faker->words(8);
        $keywords = [];
        foreach ($words as $word) {
            $keywords[$word] = round($this->faker->randomFloat(4, 0.01, 1.0), 4);
        }

        return [
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => CompetitorContentItem::factory(),
            'topics' => $this->faker->words(5),
            'entities' => $this->faker->words(3),
            'keywords' => $keywords,
            'embedding_vector' => null,
            'fingerprinted_at' => now(),
        ];
    }
}
