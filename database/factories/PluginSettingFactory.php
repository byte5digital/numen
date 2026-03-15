<?php

namespace Database\Factories;

use App\Models\Plugin;
use App\Models\PluginSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PluginSetting>
 */
class PluginSettingFactory extends Factory
{
    protected $model = PluginSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plugin_id' => Plugin::factory(),
            'space_id' => null,
            'key' => $this->faker->unique()->slug(2),
            'value' => ['data' => $this->faker->word()],
            'is_secret' => false,
        ];
    }

    public function secret(): static
    {
        return $this->state([
            'value' => ['data' => $this->faker->password()],
            'is_secret' => true,
        ]);
    }

    public function forSpace(string $spaceId): static
    {
        return $this->state(['space_id' => $spaceId]);
    }
}
