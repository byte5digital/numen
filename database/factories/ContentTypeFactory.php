<?php

namespace Database\Factories;

use App\Models\ContentType;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContentTypeFactory extends Factory
{
    protected $model = ContentType::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'space_id' => Space::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'schema' => ['fields' => []],
            'generation_config' => null,
            'seo_config' => null,
        ];
    }
}
