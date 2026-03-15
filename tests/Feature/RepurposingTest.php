<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\FormatTemplate;
use App\Models\RepurposedContent;
use App\Models\Space;
use App\Models\User;
use App\Services\FormatAdapterService;
use App\Services\FormatTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepurposingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    private Content $content;

    private FormatAdapterService $formatAdapterService;

    private FormatTemplateService $formatTemplateService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $user */
        $user = User::factory()->create();
        $this->user = $user;

        /** @var Space $space */
        $space = Space::factory()->create(['user_id' => $this->user->id]);
        $this->space = $space;

        /** @var Content $content */
        $content = Content::factory()->create(['space_id' => $this->space->id]);
        $this->content = $content;

        $this->formatAdapterService = app(FormatAdapterService::class);
        $this->formatTemplateService = app(FormatTemplateService::class);
    }

    /**
     * @test
     */
    public function can_list_repurposed_content_for_content_item(): void
    {
        RepurposedContent::factory()
            ->for($this->content)
            ->create(['format_key' => 'twitter_thread', 'status' => 'completed']);

        RepurposedContent::factory()
            ->for($this->content)
            ->create(['format_key' => 'linkedin_post', 'status' => 'pending']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/content/{$this->content->id}/repurposed");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /**
     * @test
     */
    public function can_trigger_repurposing_creates_pending_content(): void
    {
        FormatTemplate::factory()->create([
            'space_id' => $this->space->id,
            'format_key' => 'twitter_thread',
            'system_prompt' => 'You are a twitter expert.',
            'user_prompt_template' => 'Convert to twitter thread: {{body}}',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/content/{$this->content->id}/repurpose", [
                'format_key' => 'twitter_thread',
            ]);

        $response->assertCreated();

        $repurposed = RepurposedContent::where([
            'content_id' => $this->content->id,
            'format_key' => 'twitter_thread',
        ])->first();

        $this->assertNotNull($repurposed);
        $this->assertEquals('pending', $repurposed->status);
    }

    /**
     * @test
     */
    public function repurposing_validates_format_key(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/content/{$this->content->id}/repurpose", [
                'format_key' => 'invalid_format_xyz',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('format_key');
    }

    /**
     * @test
     */
    public function batch_repurposing_enforces_50_item_limit(): void
    {
        $contents = Content::factory(55)->create(['space_id' => $this->space->id]);

        FormatTemplate::factory()->create([
            'space_id' => $this->space->id,
            'format_key' => 'linkedin_post',
            'system_prompt' => 'LinkedIn expert',
            'user_prompt_template' => 'LinkedIn: {{body}}',
        ]);

        $contentIds = $contents->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson('/api/repurposing/batch', [
                'content_ids' => $contentIds,
                'format_key' => 'linkedin_post',
            ]);

        $response->assertUnprocessable();
    }

    /**
     * @test
     */
    public function batch_repurposing_works_within_limit(): void
    {
        $contents = Content::factory(40)->create(['space_id' => $this->space->id]);

        FormatTemplate::factory()->create([
            'space_id' => $this->space->id,
            'format_key' => 'twitter_thread',
            'system_prompt' => 'Twitter expert',
            'user_prompt_template' => 'Twitter: {{body}}',
        ]);

        $contentIds = $contents->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson('/api/repurposing/batch', [
                'content_ids' => $contentIds,
                'format_key' => 'twitter_thread',
            ]);

        $response->assertCreated();
    }

    /**
     * @test
     */
    public function can_get_repurposed_content_status(): void
    {
        $repurposed = RepurposedContent::factory()
            ->for($this->content)
            ->create(['status' => 'processing', 'format_key' => 'twitter_thread']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/repurposed/{$repurposed->id}");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'processing');
    }

    /**
     * @test
     */
    public function format_template_service_returns_space_specific_over_global(): void
    {
        FormatTemplate::factory()->create([
            'space_id' => null,
            'format_key' => 'twitter_thread',
            'system_prompt' => 'Global twitter',
            'user_prompt_template' => 'Global twitter template',
        ]);

        $spaceTemplate = FormatTemplate::factory()->create([
            'space_id' => $this->space->id,
            'format_key' => 'twitter_thread',
            'system_prompt' => 'Space twitter',
            'user_prompt_template' => 'Space-specific twitter template',
        ]);

        /** @var ?FormatTemplate $template */
        $template = $this->formatTemplateService->getForSpace(
            (int) $this->space->id,
            'twitter_thread'
        );

        $this->assertNotNull($template);
        $this->assertEquals($spaceTemplate->id, $template->id);
    }

    /**
     * @test
     */
    public function format_adapter_service_builds_prompt_with_placeholder_replacement(): void
    {
        /** @var FormatTemplate $template */
        $template = FormatTemplate::factory()->create([
            'system_prompt' => 'You are helpful.',
            'user_prompt_template' => 'Title: {{title}}\nBody: {{body}}',
        ]);

        /** @var Content $content */
        $content = Content::factory()->create([
            'title' => 'Test Article',
            'body' => 'This is test content',
        ]);

        $prompt = $this->formatAdapterService->buildPrompt($content, $template);

        $this->assertArrayHasKey('system', $prompt);
        $this->assertArrayHasKey('user', $prompt);
        $this->assertStringContainsString('Title: Test Article', $prompt['user']);
        $this->assertStringContainsString('Body: This is test content', $prompt['user']);
    }

    /**
     * @test
     */
    public function format_adapter_service_parses_twitter_thread_output(): void
    {
        /** @var FormatTemplate $template */
        $template = FormatTemplate::factory()->create([
            'format_key' => 'twitter_thread',
            'system_prompt' => 'Twitter',
            'user_prompt_template' => 'Convert to thread',
        ]);

        $llmOutput = "Tweet 1: First tweet in thread.\n---\nTweet 2: Second tweet.";

        $parsed = $this->formatAdapterService->parseOutput($llmOutput, $template);

        $this->assertArrayHasKey('output', $parsed);
        $this->assertArrayHasKey('output_parts', $parsed);
        $this->assertEquals($llmOutput, $parsed['output']);
    }

    /**
     * @test
     */
    public function format_adapter_service_parses_non_thread_output(): void
    {
        /** @var FormatTemplate $template */
        $template = FormatTemplate::factory()->create([
            'format_key' => 'linkedin_post',
            'system_prompt' => 'LinkedIn',
            'user_prompt_template' => 'Convert to post',
        ]);

        $llmOutput = 'This is a LinkedIn post about amazing work.';

        $parsed = $this->formatAdapterService->parseOutput($llmOutput, $template);

        $this->assertArrayHasKey('output', $parsed);
        $this->assertEquals($llmOutput, $parsed['output']);
        $this->assertNull($parsed['output_parts']);
    }

    /**
     * @test
     */
    public function unauthorized_user_cannot_trigger_repurposing(): void
    {
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/content/{$this->content->id}/repurpose", [
                'format_key' => 'twitter_thread',
            ]);

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function repurposed_content_respects_space_isolation(): void
    {
        /** @var Space $otherSpace */
        $otherSpace = Space::factory()->create();

        /** @var Content $otherContent */
        $otherContent = Content::factory()->create(['space_id' => $otherSpace->id]);

        $repurposed = RepurposedContent::factory()
            ->for($otherContent)
            ->create(['status' => 'completed']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/repurposed/{$repurposed->id}");

        $response->assertForbidden();
    }
}
