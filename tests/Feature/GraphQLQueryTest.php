<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\MediaAsset;
use App\Models\Persona;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class GraphQLQueryTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private User $adminUser;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->space = Space::factory()->create();
    }

    public function test_can_query_spaces(): void
    {
        Space::factory()->count(2)->create();

        $this->actingAs($this->adminUser);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                spaces {
                    id
                    name
                    slug
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'spaces' => [
                    '*' => ['id', 'name', 'slug'],
                ],
            ],
        ]);

        $response->assertJsonPath('data.spaces.0.name', fn ($v) => is_string($v));
    }

    public function test_can_query_content_by_slug(): void
    {
        $contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
            'slug' => 'hello-world',
            'locale' => 'en',
        ]);

        // Public query — no auth required
        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                contentBySlug(slug: "hello-world", spaceSlug: "'.$this->space->slug.'") {
                    id
                    slug
                    status
                }
            }
        ');

        $response->assertJsonPath('data.contentBySlug.slug', 'hello-world');
        $response->assertJsonPath('data.contentBySlug.status', 'published');
    }

    public function test_can_query_contents_with_pagination(): void
    {
        $contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
        Content::factory()->published()->count(5)->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        // contents is a public paginated query
        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                contents(spaceSlug: "'.$this->space->slug.'", first: 3) {
                    data {
                        id
                        slug
                    }
                    paginatorInfo {
                        total
                        hasMorePages
                    }
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'contents' => [
                    'data' => [['id', 'slug']],
                    'paginatorInfo' => ['total', 'hasMorePages'],
                ],
            ],
        ]);

        $this->assertCount(3, $response->json('data.contents.data'));
        $this->assertEquals(5, $response->json('data.contents.paginatorInfo.total'));
        $this->assertTrue($response->json('data.contents.paginatorInfo.hasMorePages'));
    }

    public function test_guard_protects_admin_queries(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                spaces {
                    id
                    name
                }
            }
        ');

        // Unauthenticated request should receive an error (guard rejects)
        $response->assertGraphQLErrorMessage('Unauthenticated.');
    }

    public function test_authenticated_user_can_query_admin_data(): void
    {
        ContentType::factory()->create(['space_id' => $this->space->id]);

        $this->actingAs($this->adminUser);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                spaces {
                    id
                    name
                    slug
                }
            }
        ');

        $response->assertJsonMissingValidationErrors();
        $response->assertJsonStructure([
            'data' => ['spaces'],
        ]);
    }

    public function test_content_includes_current_version(): void
    {
        $this->actingAs($this->adminUser);

        $contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                content(id: "'.$content->id.'") {
                    id
                    slug
                    status
                }
            }
        ');

        $response->assertJsonPath('data.content.id', $content->id);
        $response->assertJsonPath('data.content.status', 'published');
    }

    public function test_can_query_personas_without_system_prompt(): void
    {
        $this->actingAs($this->adminUser);

        Persona::factory()->create([
            'space_id' => $this->space->id,
            'system_prompt' => 'Super secret business logic — must never be exposed',
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                personas(spaceId: "'.$this->space->id.'") {
                    id
                    name
                    role
                    is_active
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'personas' => [
                    '*' => ['id', 'name', 'role'],
                ],
            ],
        ]);

        // system_prompt must NOT appear anywhere in the response
        $this->assertStringNotContainsString('system_prompt', json_encode($response->json()));
        $this->assertStringNotContainsString('Super secret', json_encode($response->json()));
    }

    public function test_can_query_media_assets(): void
    {
        $this->actingAs($this->adminUser);

        MediaAsset::factory()->count(3)->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                mediaAssets(spaceId: "'.$this->space->id.'") {
                    edges {
                        node {
                            id
                            filename
                            mime_type
                        }
                    }
                    totalCount
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'mediaAssets' => [
                    'edges' => [['node' => ['id', 'filename', 'mime_type']]],
                    'totalCount',
                ],
            ],
        ]);

        $this->assertEquals(3, $response->json('data.mediaAssets.totalCount'));
    }

    public function test_can_query_taxonomies(): void
    {
        $this->actingAs($this->adminUser);

        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
        ]);
        TaxonomyTerm::factory()->count(2)->create([
            'vocabulary_id' => $vocab->id,
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            {
                vocabularies(spaceId: "'.$this->space->id.'") {
                    id
                    name
                    slug
                    terms {
                        id
                        name
                    }
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'vocabularies' => [
                    '*' => [
                        'id', 'name', 'slug',
                        'terms' => [['id', 'name']],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $response->json('data.vocabularies.0.terms'));
    }
}
