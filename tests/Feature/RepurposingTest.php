<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\FormatTemplate;
use App\Models\RepurposedContent;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepurposingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $user */
        $user = User::factory()->create();
        $this->user = $user;

        /** @var Space $space */
        $space = Space::factory()->create();
        $this->space = $space;

        /** @var Content $content */
        $content = Content::factory()->create(['space_id' => $this->space->id]);
        $this->content = $content;
    }

    /**
     * @test
     */
    public function can_list_repurposed_content_for_content_item(): void
    {
        RepurposedContent::factory()
            ->for($this->content, 'sourceContent')
            ->create(['space_id' => $this->space->id, 'format_key' => 'twitter_thread', 'status' => 'completed']);

        RepurposedContent::factory()
            ->for($this->content, 'sourceContent')
            ->create(['space_id' => $this->space->id, 'format_key' => 'linkedin_post', 'status' => 'pending']);

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
            ->for($this->content, 'sourceContent')
            ->create(['space_id' => $this->space->id, 'status' => 'processing', 'format_key' => 'twitter_thread']);

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

        // Verify space-specific template shadows global template
        /** @var ?FormatTemplate $result */
        $result = FormatTemplate::getForSpace((int) $this->space->id, 'twitter_thread');
        $this->assertNotNull($result);
        $this->assertEquals($spaceTemplate->id, $result->id);
    }

    /**
     * @test
     */
    public function format_template_service_falls_back_to_global_template(): void
    {
        $globalTemplate = FormatTemplate::factory()->create([
            'space_id' => null,
            'format_key' => 'linkedin_post',
            'system_prompt' => 'Global linkedin',
            'user_prompt_template' => 'Global linkedin template',
        ]);

        // Verify fallback to global template when no space-specific
        /** @var ?FormatTemplate $result */
        $result = FormatTemplate::getForSpace((int) $this->space->id, 'linkedin_post');
        $this->assertNotNull($result);
        $this->assertEquals($globalTemplate->id, $result->id);
    }

    /**
     * @test
     */
    public function repurposed_content_has_correct_relationships(): void
    {
        $repurposed = RepurposedContent::factory()
            ->for($this->content, 'sourceContent')
            ->create(['space_id' => \$this->space->id, 'status' => 'completed']);

        $this->assertEquals($this->content->id, $repurposed->source_content_id);
        $this->assertNotNull($repurposed->id);
    }

    /**
     * @test
     */
    public function format_template_model_has_correct_attributes(): void
    {
        $template = FormatTemplate::factory()->create([
            'space_id' => $this->space->id,
            'format_key' => 'email',
            'system_prompt' => 'You are an email expert',
            'user_prompt_template' => 'Create email: {{body}}',
        ]);

        $this->assertEquals('email', $template->format_key);
        $this->assertEquals((int) $this->space->id, $template->space_id);
        $this->assertStringContainsString('email expert', $template->system_prompt);
    }

    /**
     * @test
     */
    public function repurposed_content_tracks_status_transitions(): void
    {
        $repurposed = RepurposedContent::factory()
            ->for($this->content, 'sourceContent')
            ->create(['space_id' => \$this->space->id, 'status' => 'pending']);

        $this->assertEquals('pending', $repurposed->status);

        $repurposed->update(['status' => 'processing']);
        $this->assertEquals('processing', $repurposed->status);

        $repurposed->update(['status' => 'completed', 'output' => 'Result here']);
        $this->assertEquals('completed', $repurposed->status);
        $this->assertEquals('Result here', $repurposed->output);
    }

    /**
     * @test
     */
    public function format_template_supports_null_space_id_for_global_templates(): void
    {
        $globalTemplate = FormatTemplate::factory()->create([
            'space_id' => null,
            'format_key' => 'sms',
            'system_prompt' => 'SMS format',
            'user_prompt_template' => 'SMS: {{body}}',
        ]);

        $this->assertNull($globalTemplate->space_id);
        $this->assertNotNull($globalTemplate->id);
    }
}
