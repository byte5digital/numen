<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentQualityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->space = Space::factory()->create();
        $this->user = User::factory()->create();

        // Assign editor role globally (covers content.view)
        $editor = Role::where('slug', 'editor')->first();
        $this->user->roles()->attach($editor->id, ['space_id' => null]);
    }

    // ── GET /api/v1/quality/scores ─────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/quality/scores?space_id='.$this->space->id)
            ->assertUnauthorized();
    }

    public function test_list_scores_returns_empty_for_no_scores(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/scores?space_id='.$this->space->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_scores_returns_scores_for_space(): void
    {
        ContentQualityScore::factory()->count(3)->create(['space_id' => $this->space->id]);
        // Score for another space should not appear
        ContentQualityScore::factory()->create();

        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/scores?space_id='.$this->space->id)
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_scores_filtered_by_content_id(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);
        ContentQualityScore::factory()->count(2)->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
        ]);
        ContentQualityScore::factory()->create(['space_id' => $this->space->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/scores?space_id='.$this->space->id.'&content_id='.$content->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── GET /api/v1/quality/scores/{score} ────────────────────────────────

    public function test_show_score_returns_score_with_items(): void
    {
        $score = ContentQualityScore::factory()
            ->has(\App\Models\ContentQualityScoreItem::factory()->count(2), 'items')
            ->create(['space_id' => $this->space->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/scores/'.$score->id)
            ->assertOk()
            ->assertJsonPath('data.id', $score->id)
            ->assertJsonCount(2, 'data.items');
    }

    // ── POST /api/v1/quality/score ─────────────────────────────────────────

    public function test_trigger_score_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/quality/score', ['content_id' => $content->id])
            ->assertStatus(202)
            ->assertJsonPath('content_id', $content->id);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ScoreContentQualityJob::class);
    }

    // ── GET /api/v1/quality/trends ─────────────────────────────────────────

    public function test_trends_returns_data_for_space(): void
    {
        ContentQualityScore::factory()->count(2)->create(['space_id' => $this->space->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/trends?space_id='.$this->space->id)
            ->assertOk()
            ->assertJsonStructure(['data' => ['trends', 'leaderboard', 'distribution', 'period']]);
    }

    // ── GET /api/v1/quality/config ─────────────────────────────────────────

    public function test_get_config_creates_default_config_if_none(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/config?space_id='.$this->space->id)
            ->assertSuccessful()
            ->assertJsonPath('data.space_id', $this->space->id)
            ->assertJsonPath('data.auto_score_on_publish', true)
            ->assertJsonPath('data.pipeline_gate_enabled', false);

        $this->assertDatabaseHas('content_quality_configs', ['space_id' => $this->space->id]);
    }

    public function test_get_config_returns_existing_config(): void
    {
        $config = ContentQualityConfig::factory()->create(['space_id' => $this->space->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/quality/config?space_id='.$this->space->id)
            ->assertOk()
            ->assertJsonPath('data.id', $config->id);
    }

    // ── PUT /api/v1/quality/config ─────────────────────────────────────────

    public function test_update_config_persists_changes(): void
    {
        // Assign admin role to allow settings.manage
        $admin = Role::where('slug', 'admin')->first();
        $this->user->roles()->attach($admin->id, ['space_id' => null]);

        $this->actingAs($this->user)
            ->putJson('/api/v1/quality/config', [
                'space_id' => $this->space->id,
                'pipeline_gate_enabled' => true,
                'pipeline_gate_min_score' => 75,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.pipeline_gate_enabled', true)
            ->assertJsonPath('data.pipeline_gate_min_score', 75);
    }

    public function test_update_config_validates_enabled_dimensions(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        $this->user->roles()->attach($admin->id, ['space_id' => null]);

        $this->actingAs($this->user)
            ->putJson('/api/v1/quality/config', [
                'space_id' => $this->space->id,
                'enabled_dimensions' => ['invalid_dimension'],
            ])
            ->assertUnprocessable();
    }
}
