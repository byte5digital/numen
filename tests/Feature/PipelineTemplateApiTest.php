<?php

namespace Tests\Feature;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateRating;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
    }

    public function test_index_lists_space_and_marketplace_templates(): void
    {
        $spaceTemplate = PipelineTemplate::factory()->forSpace($this->space)->create();
        $marketplaceTemplate = PipelineTemplate::factory()->published()->create();
        $privateOther = PipelineTemplate::factory()->create(['space_id' => Space::factory()->create()->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/pipeline-templates");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($spaceTemplate->id, $ids->all());
        $this->assertContains($marketplaceTemplate->id, $ids->all());
        $this->assertNotContains($privateOther->id, $ids->all());
    }

    public function test_show_returns_template_with_versions(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();
        PipelineTemplateVersion::factory()->latest()->create(['template_id' => $template->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonStructure(['data' => ['latest_version']]);
    }

    public function test_store_creates_template_with_version(): void
    {
        $payload = [
            'name' => 'My Template',
            'definition' => [
                'schema_version' => '1.0',
                'stages' => [['name' => 'generate', 'type' => 'ai_generate']],
            ],
            'version' => '1.0.0',
            'changelog' => 'Initial version',
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My Template');
        $this->assertDatabaseHas('pipeline_templates', ['name' => 'My Template', 'space_id' => $this->space->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'definition']);
    }

    public function test_update_modifies_template_metadata(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_destroy_soft_deletes_template(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('pipeline_templates', ['id' => $template->id]);
    }

    public function test_publish_marks_template_as_published(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create(['is_published' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.is_published', true);
    }

    public function test_unpublish_marks_template_as_unpublished(): void
    {
        $template = PipelineTemplate::factory()->create(['is_published' => true, 'space_id' => null]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/unpublish");

        $response->assertOk()
            ->assertJsonPath('data.is_published', false);
    }

    public function test_versions_index_lists_versions(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();
        PipelineTemplateVersion::factory()->count(3)->create(['template_id' => $template->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/versions");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_versions_store_creates_new_version(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/versions", [
                'version' => '1.1.0',
                'definition' => [
                    'schema_version' => '1.0',
                    'stages' => [['name' => 'generate', 'type' => 'ai_generate']],
                ],
                'changelog' => 'Second version',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.version', '1.1.0');
    }

    public function test_versions_show_returns_version(): void
    {
        $template = PipelineTemplate::factory()->forSpace($this->space)->create();
        $version = PipelineTemplateVersion::factory()->latest()->create(['template_id' => $template->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/versions/{$version->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $version->id);
    }

    public function test_ratings_index_lists_ratings(): void
    {
        $template = PipelineTemplate::factory()->create();
        PipelineTemplateRating::factory()->count(2)->create(['template_id' => $template->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/ratings");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['average_rating', 'total']]);
    }

    public function test_ratings_store_creates_rating(): void
    {
        $template = PipelineTemplate::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/ratings", [
                'rating' => 4,
                'review' => 'Great template!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.rating', 4);
    }

    public function test_ratings_validates_rating_range(): void
    {
        $template = PipelineTemplate::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/pipeline-templates/{$template->id}/ratings", [
                'rating' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }
}
