<?php

namespace Tests\Feature;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateInstall;
use App\Models\PipelineTemplateRating;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEST: Pipeline Templates & Preset Library — Smoke Tests (Chunk 1)
 *
 * Tests cover:
 * - Factory creation for all 4 models
 * - Model relationships
 * - ULID primary keys
 * - Nullable space_id for global/marketplace templates
 */
class PipelineTemplateTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PipelineTemplate
    // -------------------------------------------------------------------------

    public function test_pipeline_template_factory_creates_record(): void
    {
        $template = PipelineTemplate::factory()->create();

        $this->assertDatabaseHas('pipeline_templates', ['id' => $template->id]);
        $this->assertNotEmpty($template->id);
        $this->assertIsString($template->id);
        $this->assertEquals(26, strlen($template->id));
    }

    public function test_pipeline_template_global_has_null_space_id(): void
    {
        $template = PipelineTemplate::factory()->global()->create();

        $this->assertNull($template->space_id);
        $this->assertTrue($template->isGlobal());
    }

    public function test_pipeline_template_for_space_has_space_id(): void
    {
        $space = Space::factory()->create();
        $template = PipelineTemplate::factory()->forSpace($space)->create();

        $this->assertEquals($space->id, $template->space_id);
        $this->assertFalse($template->isGlobal());
    }

    public function test_pipeline_template_published_state(): void
    {
        $template = PipelineTemplate::factory()->published()->create();

        $this->assertTrue($template->is_published);
    }

    public function test_pipeline_template_soft_deletes(): void
    {
        $template = PipelineTemplate::factory()->create();
        $id = $template->id;

        $template->delete();

        $this->assertSoftDeleted('pipeline_templates', ['id' => $id]);
        $this->assertNotNull(PipelineTemplate::withTrashed()->find($id));
    }

    public function test_pipeline_template_has_versions_relationship(): void
    {
        $template = PipelineTemplate::factory()->create();
        PipelineTemplateVersion::factory()->count(2)->create(['template_id' => $template->id]);

        $this->assertCount(2, $template->versions);
    }

    public function test_pipeline_template_has_ratings_relationship(): void
    {
        $template = PipelineTemplate::factory()->create();
        PipelineTemplateRating::factory()->count(3)->create(['template_id' => $template->id]);

        $this->assertCount(3, $template->ratings);
    }

    public function test_pipeline_template_average_rating(): void
    {
        $template = PipelineTemplate::factory()->create();
        PipelineTemplateRating::factory()->withRating(4)->create(['template_id' => $template->id]);
        PipelineTemplateRating::factory()->withRating(2)->create(['template_id' => $template->id]);

        $this->assertEquals(3.0, $template->averageRating());
    }

    // -------------------------------------------------------------------------
    // PipelineTemplateVersion
    // -------------------------------------------------------------------------

    public function test_pipeline_template_version_factory_creates_record(): void
    {
        $version = PipelineTemplateVersion::factory()->create();

        $this->assertDatabaseHas('pipeline_template_versions', ['id' => $version->id]);
        $this->assertEquals(26, strlen($version->id));
        $this->assertIsArray($version->definition);
    }

    public function test_pipeline_template_version_latest_state(): void
    {
        $version = PipelineTemplateVersion::factory()->latest()->create();

        $this->assertTrue($version->is_latest);
        $this->assertNotNull($version->published_at);
    }

    public function test_pipeline_template_version_belongs_to_template(): void
    {
        $template = PipelineTemplate::factory()->create();
        $version = PipelineTemplateVersion::factory()->create(['template_id' => $template->id]);

        $this->assertEquals($template->id, $version->template->id);
    }

    // -------------------------------------------------------------------------
    // PipelineTemplateInstall
    // -------------------------------------------------------------------------

    public function test_pipeline_template_install_factory_creates_record(): void
    {
        $install = PipelineTemplateInstall::factory()->create();

        $this->assertDatabaseHas('pipeline_template_installs', ['id' => $install->id]);
        $this->assertEquals(26, strlen($install->id));
    }

    public function test_pipeline_template_install_with_config_overrides(): void
    {
        $overrides = ['persona_id' => 'custom-persona-id'];
        $install = PipelineTemplateInstall::factory()->withConfigOverrides($overrides)->create();

        $this->assertEquals($overrides, $install->config_overrides);
    }

    public function test_pipeline_template_install_nullable_pipeline_id(): void
    {
        $install = PipelineTemplateInstall::factory()->create(['pipeline_id' => null]);

        $this->assertNull($install->pipeline_id);
    }

    public function test_pipeline_template_install_belongs_to_template(): void
    {
        $template = PipelineTemplate::factory()->create();
        $version = PipelineTemplateVersion::factory()->create(['template_id' => $template->id]);
        $install = PipelineTemplateInstall::factory()->create([
            'template_id' => $template->id,
            'version_id' => $version->id,
        ]);

        $this->assertEquals($template->id, $install->template->id);
        $this->assertEquals($version->id, $install->templateVersion->id);
    }

    // -------------------------------------------------------------------------
    // PipelineTemplateRating
    // -------------------------------------------------------------------------

    public function test_pipeline_template_rating_factory_creates_record(): void
    {
        $rating = PipelineTemplateRating::factory()->create();

        $this->assertDatabaseHas('pipeline_template_ratings', ['id' => $rating->id]);
        $this->assertEquals(26, strlen($rating->id));
        $this->assertGreaterThanOrEqual(1, $rating->rating);
        $this->assertLessThanOrEqual(5, $rating->rating);
    }

    public function test_pipeline_template_rating_belongs_to_template(): void
    {
        $template = PipelineTemplate::factory()->create();
        $rating = PipelineTemplateRating::factory()->create(['template_id' => $template->id]);

        $this->assertEquals($template->id, $rating->template->id);
    }

    public function test_pipeline_template_rating_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $rating = PipelineTemplateRating::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $rating->user->id);
    }

    public function test_pipeline_template_rating_with_specific_rating(): void
    {
        $rating = PipelineTemplateRating::factory()->withRating(5)->create();

        $this->assertEquals(5, $rating->rating);
    }
}
