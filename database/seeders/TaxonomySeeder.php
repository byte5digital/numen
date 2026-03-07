<?php

namespace Database\Seeders;

use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /**
     * Seed default vocabularies (Categories and Tags) for all spaces.
     */
    public function run(): void
    {
        $spaces = Space::all();

        foreach ($spaces as $space) {
            $this->seedForSpace($space);
        }
    }

    private function seedForSpace(Space $space): void
    {
        // ── Categories ──────────────────────────────────────────────────────
        $categories = Vocabulary::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'categories'],
            [
                'name' => 'Categories',
                'description' => 'High-level topic categories for organizing content',
                'hierarchy' => true,
                'allow_multiple' => true,
                'sort_order' => 0,
            ]
        );

        // Seed example root categories
        $technology = $this->upsertTerm($categories, [
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Technology-related content',
            'sort_order' => 0,
        ]);

        $this->upsertTerm($categories, [
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Web development tutorials and articles',
            'parent_id' => $technology->id,
            'sort_order' => 0,
        ]);

        $this->upsertTerm($categories, [
            'name' => 'AI & Machine Learning',
            'slug' => 'ai-machine-learning',
            'description' => 'Artificial intelligence and machine learning topics',
            'parent_id' => $technology->id,
            'sort_order' => 1,
        ]);

        $this->upsertTerm($categories, [
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Business and entrepreneurship content',
            'sort_order' => 1,
        ]);

        $this->upsertTerm($categories, [
            'name' => 'Design',
            'slug' => 'design',
            'description' => 'Design, UX, and creative content',
            'sort_order' => 2,
        ]);

        // ── Tags ─────────────────────────────────────────────────────────────
        $tags = Vocabulary::firstOrCreate(
            ['space_id' => $space->id, 'slug' => 'tags'],
            [
                'name' => 'Tags',
                'description' => 'Free-form tags for flexible content discovery',
                'hierarchy' => false,
                'allow_multiple' => true,
                'sort_order' => 1,
            ]
        );

        $defaultTags = [
            ['name' => 'Tutorial', 'slug' => 'tutorial', 'sort_order' => 0],
            ['name' => 'Getting Started', 'slug' => 'getting-started', 'sort_order' => 1],
            ['name' => 'Best Practices', 'slug' => 'best-practices', 'sort_order' => 2],
            ['name' => 'Laravel', 'slug' => 'laravel', 'sort_order' => 3],
            ['name' => 'PHP', 'slug' => 'php', 'sort_order' => 4],
            ['name' => 'Vue.js', 'slug' => 'vuejs', 'sort_order' => 5],
            ['name' => 'API', 'slug' => 'api', 'sort_order' => 6],
            ['name' => 'CMS', 'slug' => 'cms', 'sort_order' => 7],
        ];

        foreach ($defaultTags as $tagData) {
            $this->upsertTerm($tags, $tagData);
        }
    }

    /**
     * Create a term if it doesn't already exist (idempotent).
     */
    private function upsertTerm(Vocabulary $vocabulary, array $data): TaxonomyTerm
    {
        return TaxonomyTerm::firstOrCreate(
            ['vocabulary_id' => $vocabulary->id, 'slug' => $data['slug']],
            array_merge($data, ['vocabulary_id' => $vocabulary->id])
        );
    }
}
