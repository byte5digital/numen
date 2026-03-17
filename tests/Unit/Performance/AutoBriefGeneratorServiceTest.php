<?php

namespace Tests\Unit\Performance;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\Performance\ContentRefreshSuggestion;
use App\Models\Space;
use App\Services\Performance\AutoBriefGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoBriefGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private AutoBriefGeneratorService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutoBriefGeneratorService::class);
        $this->space = Space::factory()->create();
    }

    public function test_generates_brief_from_suggestion(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
            'urgency_score' => 75.0,
            'suggestions' => [
                ['type' => 'update_content', 'priority' => 'high', 'detail' => 'Declining views'],
                ['type' => 'add_visuals', 'priority' => 'medium', 'detail' => 'Low scroll depth'],
            ],
            'performance_context' => [
                'current_score' => 25.0,
                'current_views' => 50,
                'bounce_rate' => 0.70,
            ],
        ]);

        $brief = $this->service->generateRefreshBrief($suggestion);

        $this->assertInstanceOf(ContentBrief::class, $brief);
        $this->assertEquals($this->space->id, $brief->space_id);
        $this->assertEquals($content->id, $brief->content_id);
        $this->assertEquals('performance_refresh', $brief->source);
        $this->assertEquals('high', $brief->priority);
        $this->assertNotEmpty($brief->requirements);
        $this->assertStringContains('Refresh:', $brief->title);
    }

    public function test_updates_suggestion_status_after_brief_generation(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
            'urgency_score' => 60.0,
            'suggestions' => [
                ['type' => 'update_statistics', 'priority' => 'high', 'detail' => 'Content is stale'],
            ],
        ]);

        $brief = $this->service->generateRefreshBrief($suggestion);

        $suggestion->refresh();
        $this->assertEquals('in_progress', $suggestion->status);
        $this->assertEquals($brief->id, $suggestion->brief_id);
        $this->assertNotNull($suggestion->acted_on_at);
    }

    public function test_brief_priority_matches_urgency_score(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $lowSuggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
            'urgency_score' => 15.0,
            'suggestions' => [
                ['type' => 'optimize_seo', 'priority' => 'low', 'detail' => 'Underperforming'],
            ],
        ]);

        $brief = $this->service->generateRefreshBrief($lowSuggestion);
        $this->assertEquals('low', $brief->priority);
    }

    public function test_brief_description_includes_performance_context(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
            'urgency_score' => 55.0,
            'performance_context' => [
                'current_score' => 30.0,
                'current_views' => 100,
                'bounce_rate' => 0.75,
            ],
            'suggestions' => [
                ['type' => 'improve_engagement', 'priority' => 'medium', 'detail' => 'High bounce rate'],
            ],
        ]);

        $brief = $this->service->generateRefreshBrief($suggestion);

        $this->assertStringContains('auto-generated', $brief->description);
        $this->assertStringContains('30.0', $brief->description);
    }

    /**
     * Custom assertion helper for string containment.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
