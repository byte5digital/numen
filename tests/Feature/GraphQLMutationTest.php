<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class GraphQLMutationTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    private User $adminUser;

    private Space $space;

    private ContentType $contentType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->space = Space::factory()->create();
        $this->contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
    }

    public function test_can_create_content(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation CreateContent($input: CreateContentInput!) {
                createContent(input: $input) {
                    id
                    slug
                    status
                }
            }
        ', [
            'input' => [
                'space_id' => $this->space->id,
                'content_type_id' => $this->contentType->id,
                'title' => 'My New Post',
                'slug' => 'my-new-post',
                'body' => '<p>Hello world</p>',
                'locale' => 'en',
                'status' => 'draft',
            ],
        ]);

        $response->assertJsonPath('data.createContent.slug', 'my-new-post');
        $response->assertJsonPath('data.createContent.status', 'draft');

        $this->assertDatabaseHas('contents', [
            'slug' => 'my-new-post',
            'status' => 'draft',
        ]);
    }

    public function test_can_publish_content(): void
    {
        $this->actingAs($this->adminUser);

        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->contentType->id,
            'status' => 'draft',
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation PublishContent($id: ID!) {
                publishContent(id: $id) {
                    id
                    status
                }
            }
        ', [
            'id' => $content->id,
        ]);

        $response->assertJsonPath('data.publishContent.id', $content->id);
        $response->assertJsonPath('data.publishContent.status', 'published');

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'status' => 'published',
        ]);
    }

    public function test_can_delete_content(): void
    {
        $this->actingAs($this->adminUser);

        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->contentType->id,
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation DeleteContent($id: ID!) {
                deleteContent(id: $id) {
                    id
                }
            }
        ', [
            'id' => $content->id,
        ]);

        $response->assertJsonPath('data.deleteContent.id', $content->id);

        $this->assertSoftDeleted('contents', ['id' => $content->id]);
    }

    public function test_can_trigger_pipeline(): void
    {
        // Use Queue::fake to prevent actual job dispatch (avoids needing real AI personas)
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->adminUser);

        // pipeline with only human_gate stages — pauses immediately without dispatching AI jobs
        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages' => [['name' => 'review', 'type' => 'human_gate']],
        ]);

        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->contentType->id,
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation TriggerPipeline($pipelineId: ID!, $contentId: ID) {
                triggerPipeline(pipelineId: $pipelineId, contentId: $contentId) {
                    id
                }
            }
        ', [
            'pipelineId' => $pipeline->id,
            'contentId' => $content->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'triggerPipeline' => ['id'],
            ],
        ]);

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_id' => $pipeline->id,
            'content_id' => $content->id,
        ]);
    }

    public function test_unauthorized_user_cannot_mutate(): void
    {
        // No actingAs — unauthenticated request
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation CreateContent($input: CreateContentInput!) {
                createContent(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'space_id' => $this->space->id,
                'content_type_id' => $this->contentType->id,
                'title' => 'Hacked Post',
                'slug' => 'hacked-post',
            ],
        ]);

        $response->assertGraphQLErrorMessage('Unauthenticated.');
        $this->assertDatabaseMissing('contents', ['slug' => 'hacked-post']);
    }

    public function test_create_content_validates_input(): void
    {
        $this->actingAs($this->adminUser);

        // Missing required fields: title and slug
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation CreateContent($input: CreateContentInput!) {
                createContent(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'space_id' => $this->space->id,
                'content_type_id' => $this->contentType->id,
                // title and slug intentionally omitted → GraphQL schema validation
            ],
        ]);

        // GraphQL schema requires title/slug → should get a validation error
        $this->assertNotNull($response->json('errors'));
    }
}
