<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\Connectors\CmsConnectorInterface;
use App\Services\Migration\TaxonomyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TaxonomyImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(array $taxonomies): TaxonomyImportService
    {
        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getTaxonomies')->andReturn($taxonomies);

        $factory = Mockery::mock(CmsConnectorFactory::class);
        $factory->shouldReceive('make')->andReturn($connector);

        return new TaxonomyImportService($factory);
    }

    private function makeSession(): MigrationSession
    {
        return MigrationSession::factory()->create([
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
            'credentials' => 'test-key',
            'status' => 'mapping',
        ]);
    }

    public function test_imports_flat_taxonomy(): void
    {
        $service = $this->makeService([
            [
                'name' => 'Categories',
                'slug' => 'categories',
                'hierarchical' => false,
                'terms' => [
                    ['id' => 'src-1', 'name' => 'Tech', 'slug' => 'tech'],
                    ['id' => 'src-2', 'name' => 'Science', 'slug' => 'science'],
                ],
            ],
        ]);

        $session = $this->makeSession();
        $mapping = $service->importTaxonomies($session);

        $this->assertCount(2, $mapping);
        $this->assertNotNull($mapping->get('src-1'));
        $this->assertNotNull($mapping->get('src-2'));

        $vocab = Vocabulary::where('slug', 'categories')->first();
        $this->assertNotNull($vocab);
        $this->assertSame(2, $vocab->terms()->count());
    }

    public function test_imports_hierarchical_taxonomy(): void
    {
        $service = $this->makeService([
            [
                'name' => 'Categories',
                'slug' => 'categories',
                'hierarchical' => true,
                'terms' => [
                    [
                        'id' => 'parent-1',
                        'name' => 'Tech',
                        'slug' => 'tech',
                        'children' => [
                            ['id' => 'child-1', 'name' => 'AI', 'slug' => 'ai'],
                            ['id' => 'child-2', 'name' => 'Web', 'slug' => 'web'],
                        ],
                    ],
                ],
            ],
        ]);

        $session = $this->makeSession();
        $mapping = $service->importTaxonomies($session);

        $this->assertCount(3, $mapping);

        $parentTerm = TaxonomyTerm::find($mapping->get('parent-1'));
        $this->assertNotNull($parentTerm);
        $this->assertNull($parentTerm->parent_id);

        $childTerm = TaxonomyTerm::find($mapping->get('child-1'));
        $this->assertNotNull($childTerm);
        $this->assertSame($parentTerm->id, $childTerm->parent_id);
    }

    public function test_deduplicates_existing_vocabulary(): void
    {
        $session = $this->makeSession();

        Vocabulary::create([
            'space_id' => $session->space_id,
            'name' => 'Categories',
            'slug' => 'categories',
            'hierarchy' => false,
            'allow_multiple' => true,
            'sort_order' => 0,
        ]);

        $service = $this->makeService([
            [
                'name' => 'Categories',
                'slug' => 'categories',
                'terms' => [
                    ['id' => 'src-1', 'name' => 'Tech', 'slug' => 'tech'],
                ],
            ],
        ]);

        $service->importTaxonomies($session);

        $this->assertSame(1, Vocabulary::where('slug', 'categories')
            ->where('space_id', $session->space_id)
            ->count());
    }

    public function test_deduplicates_existing_terms(): void
    {
        $session = $this->makeSession();

        $vocab = Vocabulary::create([
            'space_id' => $session->space_id,
            'name' => 'Tags',
            'slug' => 'tags',
            'hierarchy' => false,
            'allow_multiple' => true,
            'sort_order' => 0,
        ]);

        $existingTerm = TaxonomyTerm::create([
            'vocabulary_id' => $vocab->id,
            'name' => 'Tech',
            'slug' => 'tech',
            'sort_order' => 0,
        ]);

        $service = $this->makeService([
            [
                'name' => 'Tags',
                'slug' => 'tags',
                'terms' => [
                    ['id' => 'src-1', 'name' => 'Tech', 'slug' => 'tech'],
                    ['id' => 'src-2', 'name' => 'Science', 'slug' => 'science'],
                ],
            ],
        ]);

        $mapping = $service->importTaxonomies($session);

        // src-1 should map to existing term
        $this->assertSame($existingTerm->id, $mapping->get('src-1'));
        // src-2 should be created
        $this->assertNotNull($mapping->get('src-2'));
        // Total terms: 2 (1 existing + 1 new)
        $this->assertSame(2, TaxonomyTerm::where('vocabulary_id', $vocab->id)->count());
    }

    public function test_handles_empty_taxonomies(): void
    {
        $service = $this->makeService([]);
        $session = $this->makeSession();
        $mapping = $service->importTaxonomies($session);

        $this->assertCount(0, $mapping);
    }

    public function test_handles_terms_without_ids(): void
    {
        $service = $this->makeService([
            [
                'name' => 'Tags',
                'slug' => 'tags',
                'terms' => [
                    ['name' => 'NoId', 'slug' => 'noid'],
                ],
            ],
        ]);

        $session = $this->makeSession();
        $mapping = $service->importTaxonomies($session);

        // No source ID means no mapping entry, but term should still be created
        $this->assertCount(0, $mapping);
        $vocab = Vocabulary::where('slug', 'tags')->first();
        $this->assertSame(1, $vocab->terms()->count());
    }
}
