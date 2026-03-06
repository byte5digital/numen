<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Persona;
use App\Models\PipelineRun;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::create([
            'name' => 'Test Space',
            'slug' => 'test',
        ]);
    }

    public function test_space_has_content_types(): void
    {
        ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);

        $this->assertCount(1, $this->space->contentTypes);
    }

    public function test_content_versioning(): void
    {
        $type = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);

        $content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'test-post',
            'status' => 'draft',
        ]);

        $v1 = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Version 1',
            'body' => 'First draft',
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'creator',
        ]);

        $v2 = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 2,
            'title' => 'Version 2',
            'body' => 'SEO optimized',
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'seo_expert',
        ]);

        $content->update(['current_version_id' => $v2->id]);

        $this->assertCount(2, $content->versions);
        $this->assertEquals('Version 2', $content->currentVersion->title);
        $this->assertTrue($v1->isAiGenerated());
    }

    public function test_content_publish(): void
    {
        $type = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);

        $content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'publish-test',
            'status' => 'draft',
        ]);

        $this->assertNull($content->published_at);

        $content->publish();

        $this->assertEquals('published', $content->status);
        $this->assertNotNull($content->published_at);
        $this->assertNotNull($content->refresh_at);
    }

    public function test_content_scopes(): void
    {
        $type = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);

        Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'published-en',
            'status' => 'published',
            'locale' => 'en',
            'published_at' => now(),
        ]);

        Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'published-de',
            'status' => 'published',
            'locale' => 'de',
            'published_at' => now(),
        ]);

        Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'draft-en',
            'status' => 'draft',
            'locale' => 'en',
        ]);

        $this->assertCount(2, Content::published()->get());
        $this->assertCount(1, Content::published()->forLocale('de')->get());
        $this->assertCount(2, Content::published()->ofType('blog')->get());
    }

    public function test_persona_model_config(): void
    {
        $persona = Persona::create([
            'space_id' => $this->space->id,
            'name' => 'Writer',
            'role' => 'creator',
            'system_prompt' => 'You are a writer.',
            'capabilities' => ['content_generation'],
            'model_config' => [
                'model' => 'claude-sonnet-4-20250514',
                'temperature' => 0.9,
                'max_tokens' => 8192,
            ],
        ]);

        $this->assertEquals('claude-sonnet-4-20250514', $persona->getModel());
        $this->assertEquals(0.9, $persona->getTemperature());
        $this->assertEquals(8192, $persona->getMaxTokens());
    }

    public function test_pipeline_stage_navigation(): void
    {
        $pipeline = ContentPipeline::create([
            'space_id' => $this->space->id,
            'name' => 'Test Pipeline',
            'stages' => [
                ['name' => 'generate', 'type' => 'ai_generate'],
                ['name' => 'seo', 'type' => 'ai_transform'],
                ['name' => 'review', 'type' => 'ai_review'],
                ['name' => 'publish', 'type' => 'auto_publish'],
            ],
        ]);

        $this->assertNotNull($pipeline->getStageByName('seo'));
        $this->assertEquals('seo', $pipeline->getStageAfter('generate')['name']);
        $this->assertEquals('review', $pipeline->getStageAfter('seo')['name']);
        $this->assertEquals('publish', $pipeline->getStageAfter('review')['name']);
        $this->assertNull($pipeline->getStageAfter('publish'));
    }

    public function test_pipeline_run_stage_results(): void
    {
        $pipeline = ContentPipeline::create([
            'space_id' => $this->space->id,
            'name' => 'Test Pipeline',
            'stages' => [['name' => 'generate', 'type' => 'ai_generate']],
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'current_stage' => 'generate',
            'started_at' => now(),
        ]);

        $run->addStageResult('generate', ['success' => true, 'score' => 85]);

        $this->assertEquals(85, $run->fresh()->stage_results['generate']['score']);

        $run->markCompleted();
        $this->assertEquals('completed', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->completed_at);
    }
}
