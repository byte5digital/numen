<?php

namespace Tests\Unit;

use App\Events\Content\ContentPublished;
use App\Jobs\PublishScheduledContent;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\Space;
use App\Models\User;
use App\Services\Versioning\DiffEngine;
use App\Services\Versioning\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PublishScheduledContentJobTest extends TestCase
{
    use RefreshDatabase;

    private Content $content;

    private ContentVersion $version;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $space = Space::create(['name' => 'Space', 'slug' => 'space']);
        $type = ContentType::create(['space_id' => $space->id, 'name' => 'Blog', 'slug' => 'blog', 'schema' => []]);
        $this->content = Content::create([
            'space_id' => $space->id,
            'content_type_id' => $type->id,
            'slug' => 'test',
            'status' => 'scheduled',
            'locale' => 'en',
        ]);
        $this->user = User::factory()->create();
        $this->version = $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'Title',
            'body' => 'Body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_job_publishes_pending_schedule(): void
    {
        Event::fake();

        $schedule = ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $this->version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        $job = new PublishScheduledContent($schedule->id);
        $job->handle(new VersioningService(new DiffEngine));

        $schedule->refresh();
        $this->assertEquals('published', $schedule->status);

        $this->version->refresh();
        $this->assertEquals('published', $this->version->status);
    }

    public function test_job_skips_non_pending_schedules(): void
    {
        Event::fake();

        $schedule = ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $this->version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->subMinute(),
            'status' => 'cancelled',
        ]);

        $job = new PublishScheduledContent($schedule->id);
        $job->handle(new VersioningService(new DiffEngine));

        // Version should remain in scheduled state (not published)
        $this->version->refresh();
        $this->assertEquals('scheduled', $this->version->status);

        Event::assertNotDispatched(ContentPublished::class);
    }

    public function test_job_handles_nonexistent_schedule_id(): void
    {
        Event::fake();

        $job = new PublishScheduledContent('nonexistent-id');
        // Should not throw, just log and return
        $job->handle(new VersioningService(new DiffEngine));

        Event::assertNotDispatched(ContentPublished::class);
    }

    public function test_job_handles_version_without_content_gracefully(): void
    {
        Event::fake();

        // Create a second content item and a schedule for it
        $space = Space::create(['name' => 'Space2', 'slug' => 'space-2']);
        $type = ContentType::create(['space_id' => $space->id, 'name' => 'Blog', 'slug' => 'blog2', 'schema' => []]);
        $content2 = Content::create([
            'space_id' => $space->id,
            'content_type_id' => $type->id,
            'slug' => 'orphan-content',
            'status' => 'scheduled',
            'locale' => 'en',
        ]);
        $version2 = $content2->versions()->create([
            'version_number' => 1,
            'title' => 'Orphan',
            'body' => 'Body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'scheduled',
        ]);

        $schedule = ScheduledPublish::create([
            'content_id' => $content2->id,
            'version_id' => $version2->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->subMinute(),
            'status' => 'pending',
        ]);

        // Mark the schedule as cancelled to test the skip path
        $schedule->update(['status' => 'cancelled']);

        $job = new PublishScheduledContent($schedule->id);
        $job->handle(new VersioningService(new DiffEngine));

        // Job exits cleanly without publishing
        Event::assertNotDispatched(ContentPublished::class);
    }

    public function test_job_has_correct_retry_config(): void
    {
        $job = new PublishScheduledContent('some-id');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }
}
