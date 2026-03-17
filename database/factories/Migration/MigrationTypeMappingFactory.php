<?php

namespace Database\Factories\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class MigrationTypeMappingFactory extends Factory
{
    protected $model = MigrationTypeMapping::class;

    public function definition(): array
    {
        $sourceTypes = ['post', 'page', 'product', 'article', 'category', 'tag'];
        $sourceKey = $this->faker->randomElement($sourceTypes);

        return [
            'migration_session_id' => MigrationSession::factory(),
            'space_id' => fn (array $attributes) => MigrationSession::find($attributes['migration_session_id'])?->space_id,
            'source_type_key' => $sourceKey,
            'source_type_label' => ucfirst($sourceKey),
            'numen_content_type_id' => null,
            'numen_type_slug' => null,
            'field_map' => [
                'title' => 'title',
                'content' => 'body',
                'excerpt' => 'summary',
            ],
            'ai_suggestions' => null,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => 'approved',
        ]);
    }
}
