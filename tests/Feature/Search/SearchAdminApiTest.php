<?php

namespace Tests\Feature\Search;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\PromotedResult;
use App\Models\SearchSynonym;
use App\Models\Space;
use App\Models\User;
use App\Services\Search\SynonymSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles so admin role exists
        $this->seed(\Database\Seeders\RoleSeeder::class);

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        // Assign admin role to user
        $adminRole = \App\Models\Role::where('slug', 'admin')->firstOrFail();
        $admin->roles()->attach($adminRole);
        $this->admin = $admin;
        /** @var Space $space */
        $space = Space::factory()->create();
        $this->space = $space;

        // Stub out SynonymSyncService to avoid Meilisearch calls
        $this->app->bind(SynonymSyncService::class, function () {
            $mock = $this->createMock(SynonymSyncService::class);
            // syncToMeilisearch is void — no willReturn needed
            $mock->method('syncToMeilisearch');

            return $mock;
        });
    }

    // ── Synonyms CRUD ─────────────────────────────────────────────────────────

    public function test_list_synonyms_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/search/synonyms?space_id='.$this->space->id);

        $response->assertUnauthorized();
    }

    public function test_list_synonyms_returns_empty_array_when_none_exist(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/search/synonyms?space_id='.$this->space->id);

        $response->assertOk();
        $response->assertJson(['data' => []]);
    }

    public function test_list_synonyms_returns_synonyms_for_space(): void
    {
        SearchSynonym::create([
            'space_id' => $this->space->id,
            'term' => 'laravel',
            'synonyms' => ['php framework', 'artisan'],
            'is_one_way' => false,
            'source' => 'manual',
            'approved' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/search/synonyms?space_id='.$this->space->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('laravel', $data[0]['term']);
    }

    public function test_create_synonym_stores_and_returns_201(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/search/synonyms', [
                'space_id' => $this->space->id,
                'term' => 'cms',
                'synonyms' => ['content management', 'headless cms'],
                'is_one_way' => false,
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['term' => 'cms']);
        $this->assertDatabaseHas('search_synonyms', [
            'space_id' => $this->space->id,
            'term' => 'cms',
        ]);
    }

    public function test_create_synonym_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/search/synonyms', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['space_id', 'term', 'synonyms']);
    }

    public function test_update_synonym_modifies_existing_record(): void
    {
        /** @var SearchSynonym $synonym */
        $synonym = SearchSynonym::create([
            'space_id' => $this->space->id,
            'term' => 'php',
            'synonyms' => ['hypertext preprocessor'],
            'is_one_way' => false,
            'source' => 'manual',
            'approved' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/search/synonyms/{$synonym->id}", [
                'synonyms' => ['hypertext preprocessor', 'scripting language'],
                'is_one_way' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('search_synonyms', [
            'id' => $synonym->id,
            'is_one_way' => true,
        ]);
    }

    public function test_update_synonym_returns_404_for_non_existent_id(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/admin/search/synonyms/nonexistent-id', ['term' => 'test']);

        $response->assertNotFound();
    }

    public function test_delete_synonym_removes_record(): void
    {
        /** @var SearchSynonym $synonym */
        $synonym = SearchSynonym::create([
            'space_id' => $this->space->id,
            'term' => 'delete-me',
            'synonyms' => ['gone'],
            'is_one_way' => false,
            'source' => 'manual',
            'approved' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/search/synonyms/{$synonym->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('search_synonyms', ['id' => $synonym->id]);
    }

    public function test_delete_synonym_returns_404_for_non_existent_id(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/v1/admin/search/synonyms/nonexistent-id');

        $response->assertNotFound();
    }

    // ── Promoted Results CRUD ─────────────────────────────────────────────────

    public function test_list_promoted_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/search/promoted?space_id='.$this->space->id);

        $response->assertUnauthorized();
    }

    public function test_list_promoted_returns_empty_when_none_exist(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/search/promoted?space_id='.$this->space->id);

        $response->assertOk();
        $response->assertJson(['data' => []]);
    }

    public function test_create_promoted_result_stores_and_returns_201(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        /** @var Content $content */
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/search/promoted', [
                'space_id' => $this->space->id,
                'query' => 'getting started',
                'content_id' => $content->id,
                'position' => 1,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('promoted_results', [
            'space_id' => $this->space->id,
            'query' => 'getting started',
            'content_id' => $content->id,
        ]);
    }

    public function test_create_promoted_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/search/promoted', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['space_id', 'query', 'content_id']);
    }

    public function test_update_promoted_result(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        /** @var Content $content */
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        /** @var PromotedResult $promoted */
        $promoted = PromotedResult::create([
            'space_id' => $this->space->id,
            'query' => 'old query',
            'content_id' => $content->id,
            'position' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/search/promoted/{$promoted->id}", [
                'query' => 'new query',
                'position' => 2,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('promoted_results', [
            'id' => $promoted->id,
            'query' => 'new query',
            'position' => 2,
        ]);
    }

    public function test_delete_promoted_result(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        /** @var Content $content */
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        /** @var PromotedResult $promoted */
        $promoted = PromotedResult::create([
            'space_id' => $this->space->id,
            'query' => 'test query',
            'content_id' => $content->id,
            'position' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/search/promoted/{$promoted->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('promoted_results', ['id' => $promoted->id]);
    }

    // ── Search Health ─────────────────────────────────────────────────────────

    public function test_health_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/search/health');

        $response->assertUnauthorized();
    }

    public function test_health_endpoint_returns_capabilities_and_stats(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/search/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'capabilities' => ['instant', 'semantic', 'ask'],
                'embeddings_count',
                'embedding_model',
            ],
        ]);
    }

    // ── Reindex ───────────────────────────────────────────────────────────────

    public function test_reindex_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/admin/search/reindex');

        $response->assertUnauthorized();
    }

    public function test_reindex_returns_count_of_dispatched_jobs(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        Content::factory()->published()->count(3)->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/search/reindex', [
                'space_id' => $this->space->id,
            ]);

        $response->assertOk();
        $this->assertSame(3, $response->json('data.count'));
    }

    // ── Analytics ────────────────────────────────────────────────────────────

    public function test_analytics_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/search/analytics?space_id='.$this->space->id);

        $response->assertUnauthorized();
    }

    public function test_analytics_endpoint_returns_dashboard_data(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/search/analytics?space_id='.$this->space->id.'&period=7d');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    // ── Authorization: Non-Admin Cannot Access Admin Endpoints ───────────────

    public function test_editor_cannot_access_admin_synonym_list(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor, 'sanctum')
            ->getJson('/api/v1/admin/search/synonyms?space_id='.$this->space->id);

        // Auth passes (sanctum) but authorization fails — only admins can access
        $response->assertForbidden();
    }
}
