<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CliTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
    }

    // ─── numen:content:list ───────────────────────────────────────────────────

    public function test_content_list_shows_no_items_when_empty(): void
    {
        $this->artisan('numen:content:list')
            ->assertSuccessful()
            ->expectsOutput('No content items found.');
    }

    public function test_content_list_shows_content_items(): void
    {
        Content::factory()->count(3)->create(['space_id' => $this->space->id]);

        $this->artisan('numen:content:list')
            ->assertSuccessful();
    }

    public function test_content_list_filters_by_status(): void
    {
        Content::factory()->create(['space_id' => $this->space->id, 'status' => 'published']);
        Content::factory()->create(['space_id' => $this->space->id, 'status' => 'draft']);

        $this->artisan('numen:content:list --status=published')
            ->assertSuccessful();
    }

    // ─── numen:content:export ─────────────────────────────────────────────────

    public function test_content_export_warns_when_no_items(): void
    {
        $this->artisan('numen:content:export')
            ->assertSuccessful();
    }

    public function test_content_export_json_to_stdout(): void
    {
        Content::factory()->create(['space_id' => $this->space->id]);

        $this->artisan('numen:content:export --format=json')
            ->assertSuccessful();
    }

    public function test_content_export_markdown_to_stdout(): void
    {
        Content::factory()->create(['space_id' => $this->space->id]);

        $this->artisan('numen:content:export --format=markdown')
            ->assertSuccessful();
    }

    public function test_content_export_fails_with_invalid_format(): void
    {
        Content::factory()->create(['space_id' => $this->space->id]);

        $this->artisan('numen:content:export --format=xml')
            ->assertFailed();
    }

    public function test_content_export_writes_to_file(): void
    {
        Content::factory()->create(['space_id' => $this->space->id]);
        $tmpFile = tempnam(sys_get_temp_dir(), 'numen_export_');

        $this->artisan("numen:content:export --output={$tmpFile}")
            ->assertSuccessful();

        $this->assertFileExists($tmpFile);
        $decoded = json_decode(file_get_contents($tmpFile), true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);

        unlink($tmpFile);
    }

    // ─── numen:brief:list ─────────────────────────────────────────────────────

    public function test_brief_list_shows_no_items_when_empty(): void
    {
        $this->artisan('numen:brief:list')
            ->assertSuccessful()
            ->expectsOutput('No briefs found.');
    }

    public function test_brief_list_shows_briefs(): void
    {
        ContentBrief::factory()->count(2)->create(['space_id' => $this->space->id]);

        $this->artisan('numen:brief:list')
            ->assertSuccessful();
    }

    public function test_brief_list_filters_by_status(): void
    {
        ContentBrief::factory()->create(['space_id' => $this->space->id, 'status' => 'completed']);
        ContentBrief::factory()->create(['space_id' => $this->space->id, 'status' => 'pending']);

        $this->artisan('numen:brief:list --status=completed')
            ->assertSuccessful();
    }

    // ─── numen:brief:create ───────────────────────────────────────────────────

    public function test_brief_create_without_title_fails(): void
    {
        $this->artisan('numen:brief:create --no-interaction')
            ->assertFailed();
    }

    public function test_brief_create_creates_brief_record(): void
    {
        Bus::fake();

        $this->artisan("numen:brief:create --title=\"Test Brief\" --space-id={$this->space->id} --no-run")
            ->assertSuccessful();

        $this->assertDatabaseHas('content_briefs', [
            'title' => 'Test Brief',
            'space_id' => $this->space->id,
            'source' => 'cli',
        ]);
    }

    public function test_brief_create_triggers_pipeline_run(): void
    {
        Bus::fake();

        ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
        ]);

        $this->artisan("numen:brief:create --title=\"Pipeline Brief\" --space-id={$this->space->id}")
            ->assertSuccessful();

        $this->assertDatabaseHas('content_briefs', ['title' => 'Pipeline Brief']);
        $this->assertDatabaseCount('pipeline_runs', 1);
    }

    // ─── numen:pipeline:status ────────────────────────────────────────────────

    public function test_pipeline_status_shows_no_runs_when_empty(): void
    {
        $this->artisan('numen:pipeline:status')
            ->assertSuccessful()
            ->expectsOutput('No pipeline runs found.');
    }

    public function test_pipeline_status_shows_runs(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status' => 'running',
            'stage_results' => [],
            'started_at' => now(),
        ]);

        $this->artisan('numen:pipeline:status')
            ->assertSuccessful();
    }

    public function test_pipeline_status_running_filter(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status' => 'completed',
            'stage_results' => [],
        ]);

        $this->artisan('numen:pipeline:status --running')
            ->assertSuccessful()
            ->expectsOutput('No pipeline runs found.');
    }

    // ─── numen:pipeline:run ───────────────────────────────────────────────────

    public function test_pipeline_run_fails_without_brief_id(): void
    {
        $this->artisan('numen:pipeline:run --no-interaction')
            ->assertFailed();
    }

    public function test_pipeline_run_fails_with_nonexistent_brief(): void
    {
        $this->artisan('numen:pipeline:run --brief-id=nonexistent')
            ->assertFailed();
    }

    public function test_pipeline_run_starts_run_for_existing_brief(): void
    {
        Bus::fake();

        ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
        ]);

        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $this->artisan("numen:pipeline:run --brief-id={$brief->id}")
            ->assertSuccessful();

        $this->assertDatabaseCount('pipeline_runs', 1);
    }

    // ─── numen:status ─────────────────────────────────────────────────────────

    public function test_status_command_completes_successfully(): void
    {
        $this->artisan('numen:status')
            ->assertSuccessful();
    }
}
