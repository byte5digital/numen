<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'filename' => $this->faker->word().'.jpg',
            'disk' => 'public',
            'path' => 'media/'.$this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(1000, 5000000),
            'source' => 'upload',
            'alt_text' => $this->faker->sentence(),
            'caption' => $this->faker->text(100),
            'is_public' => true,
            'folder_id' => null,
            'width' => 1920,
            'height' => 1080,
        ];
    }
}
