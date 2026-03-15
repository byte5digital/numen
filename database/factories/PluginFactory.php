<?php

namespace Database\Factories;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = 'numen-'.Str::slug($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'display_name' => $this->faker->words(2, true),
            'version' => $this->faker->semver(),
            'description' => $this->faker->sentence(),
            'status' => 'discovered',
            'manifest' => [
                'name' => $name,
                'version' => '1.0.0',
                'display_name' => $this->faker->words(2, true),
                'provider_class' => 'Numen\\Plugins\\'.Str::studly($name).'\\ServiceProvider',
                'api_version' => '1.0',
                'hooks' => [],
                'permissions' => [],
                'settings_schema' => [],
                'author' => $this->faker->name(),
            ],
            'installed_at' => null,
            'activated_at' => null,
            'error_message' => null,
        ];
    }

    public function discovered(): static
    {
        return $this->state(['status' => 'discovered']);
    }

    public function installed(): static
    {
        return $this->state([
            'status' => 'installed',
            'installed_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'installed_at' => now()->subMinute(),
            'activated_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'status' => 'inactive',
            'installed_at' => now()->subDay(),
            'activated_at' => null,
        ]);
    }

    public function errored(): static
    {
        return $this->state([
            'status' => 'error',
            'error_message' => $this->faker->sentence(),
        ]);
    }
}
