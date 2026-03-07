<?php

namespace Tests\Unit;

use App\Models\AIGenerationLog;
use App\Models\Content;
use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Services\Versioning\PipelineVersioningIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineVersioningIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private PipelineVersioningIntegration $integration;

    private Content $content;

    private PipelineRun $pipelineRun;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integration = new PipelineVersioningIntegration;

        $this->space = Space::create(['name' => 'Space', 'slug' => 'space']);
        $type = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);
        $this->content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'ai-content',
            'status' => 'draft',
            'locale' => 'en',
        ]);

        $pipeline = ContentPipeline::create([
            'space_id' => $this->space->id,
            'name' => 'Test Pipeline',
            'stages' => [['name' => 'generate', 'type' => 'ai_generate']],
            'is_active' => true,
        ]);

        $this->pipelineRun = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'content_id' => $this->content->id,
            'status' => 'completed',
            'stage_results' => ['generate' => ['status' => 'done']],
        ]);
    }

    public function test_on_pipeline_complete_creates_content_version(): void
    {
        $generatedContent = [
            'title' => 'AI Generated Article',
            'body' => 'This is the AI-generated body.',
            'excerpt' => 'Short excerpt',
        ];

        $version = $this->integration->onPipelineComplete($this->pipelineRun, $generatedContent);

        $this->assertInstanceOf(ContentVersion::class, $version);
        $this->assertEquals('AI Generated Article', $version->title);
        $this->assertEquals('This is the AI-generated body.', $version->body);
        $this->assertEquals('Short excerpt', $version->excerpt);
    }

    public function test_on_pipeline_complete_sets_ai_author(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertEquals('ai_agent', $version->author_type);
        $this->assertEquals((string) $this->pipelineRun->pipeline_id, $version->author_id);
    }

    public function test_on_pipeline_complete_creates_draft_status(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertEquals('draft', $version->status);
    }

    public function test_on_pipeline_complete_assigns_pipeline_run_id(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertEquals($this->pipelineRun->id, $version->pipeline_run_id);
    }

    public function test_on_pipeline_complete_stores_ai_metadata(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertNotNull($version->ai_metadata);
        $this->assertIsArray($version->ai_metadata);
        $this->assertArrayHasKey('pipeline_id', $version->ai_metadata);
        $this->assertArrayHasKey('pipeline_run_id', $version->ai_metadata);
        $this->assertArrayHasKey('stages_completed', $version->ai_metadata);
        $this->assertArrayHasKey('generated_at', $version->ai_metadata);
    }

    public function test_on_pipeline_complete_sets_version_number(): void
    {
        // Create an existing version first
        $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'Manual V1',
            'body' => 'manual',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => 'user-1',
            'status' => 'published',
        ]);

        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI V2',
            'body' => 'AI body',
        ]);

        $this->assertEquals(2, $version->version_number);
    }

    public function test_on_pipeline_complete_sets_parent_to_current_version(): void
    {
        $currentVersion = $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'Current',
            'body' => 'current body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => 'user-1',
            'status' => 'published',
        ]);
        $this->content->update(['current_version_id' => $currentVersion->id]);

        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Generated',
            'body' => 'AI body',
        ]);

        $this->assertEquals($currentVersion->id, $version->parent_version_id);
    }

    public function test_on_pipeline_complete_creates_blocks_from_generated_content(): void
    {
        $generatedContent = [
            'title' => 'AI Title',
            'body' => 'AI body',
            'blocks' => [
                ['type' => 'text', 'data' => ['text' => 'Block 1']],
                ['type' => 'image', 'data' => ['url' => 'https://example.com/img.jpg']],
            ],
        ];

        $version = $this->integration->onPipelineComplete($this->pipelineRun, $generatedContent);

        $this->assertCount(2, $version->blocks);
        $this->assertEquals('text', $version->blocks[0]->type);
        $this->assertEquals('image', $version->blocks[1]->type);
        $this->assertEquals(0, $version->blocks[0]->sort_order);
        $this->assertEquals(1, $version->blocks[1]->sort_order);
    }

    public function test_on_pipeline_complete_without_blocks(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertCount(0, $version->blocks);
    }

    public function test_on_pipeline_complete_stores_quality_and_seo_scores(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
            'quality_score' => 88.5,
            'seo_score' => 92.0,
        ]);

        $this->assertEquals('88.50', $version->quality_score);
        $this->assertEquals('92.00', $version->seo_score);
    }

    public function test_on_pipeline_complete_computes_content_hash(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertNotNull($version->content_hash);
        $this->assertEquals(64, strlen($version->content_hash));
    }

    public function test_on_pipeline_complete_includes_generation_log_data(): void
    {
        AIGenerationLog::create([
            'pipeline_run_id' => $this->pipelineRun->id,
            'model' => 'claude-opus-4-5',
            'purpose' => 'content_generation',
            'messages' => [['role' => 'user', 'content' => 'Generate content']],
            'response' => 'Generated content text response',
            'input_tokens' => 500,
            'output_tokens' => 1200,
            'total_tokens' => 1700,
            'cost_usd' => 0.025,
            'latency_ms' => 1500,
        ]);

        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertArrayHasKey('total_tokens', $version->ai_metadata);
        $this->assertArrayHasKey('total_cost_usd', $version->ai_metadata);
        $this->assertArrayHasKey('models_used', $version->ai_metadata);
        $this->assertContains('claude-opus-4-5', $version->ai_metadata['models_used']);
    }

    public function test_on_pipeline_complete_throws_when_no_content(): void
    {
        $pipeline = ContentPipeline::create([
            'space_id' => $this->space->id,
            'name' => 'Orphan Pipeline',
            'stages' => [],
            'is_active' => true,
        ]);

        $orphanRun = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'content_id' => null,
            'status' => 'completed',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no associated content/i');

        $this->integration->onPipelineComplete($orphanRun, [
            'title' => 'Title',
            'body' => 'Body',
        ]);
    }

    public function test_pipeline_version_sets_change_reason_with_pipeline_name(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->assertStringContainsString('Pipeline run', $version->change_reason ?? '');
        $this->assertStringContainsString('Test Pipeline', $version->change_reason ?? '');
    }

    public function test_pipeline_version_stores_seo_data(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
            'seo_data' => [
                'meta_description' => 'AI-generated meta description',
                'og_title' => 'AI Open Graph Title',
            ],
        ]);

        $this->assertIsArray($version->seo_data);
        $this->assertEquals('AI-generated meta description', $version->seo_data['meta_description']);
    }

    public function test_pipeline_version_is_linked_to_pipeline_run(): void
    {
        $version = $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        // Via pipelineRun relationship on ContentVersion
        $this->assertEquals($this->pipelineRun->id, $version->pipelineRun->id);
    }

    public function test_pipeline_run_versions_relationship(): void
    {
        $this->integration->onPipelineComplete($this->pipelineRun, [
            'title' => 'AI Title',
            'body' => 'AI body',
        ]);

        $this->pipelineRun->load('versions');
        $this->assertCount(1, $this->pipelineRun->versions);
    }
}
