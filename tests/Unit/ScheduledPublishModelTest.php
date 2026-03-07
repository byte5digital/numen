<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPublishModelTest extends TestCase
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
            'status' => 'draft',
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
            'status' => 'draft',
        ]);
    }

    public function test_scope_pending_returns_only_pending_records(): void
    {
        $this->makeSchedule('pending', now()->addHour());
        $this->makeSchedule('cancelled', now()->addHour());
        $this->makeSchedule('published', now()->addHour());

        $pending = ScheduledPublish::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    public function test_scope_due_returns_pending_records_past_publish_at(): void
    {
        $this->makeSchedule('pending', now()->subMinute()); // due
        $this->makeSchedule('pending', now()->addHour());   // not yet due
        $this->makeSchedule('cancelled', now()->subMinute()); // wrong status

        $due = ScheduledPublish::due()->get();

        $this->assertCount(1, $due);
    }

    public function test_scope_due_excludes_future_schedules(): void
    {
        $this->makeSchedule('pending', now()->addHour());

        $this->assertCount(0, ScheduledPublish::due()->get());
    }

    public function test_content_relationship(): void
    {
        $schedule = $this->makeSchedule('pending', now()->addHour());

        $this->assertEquals($this->content->id, $schedule->content->id);
    }

    public function test_version_relationship(): void
    {
        $schedule = $this->makeSchedule('pending', now()->addHour());

        $this->assertEquals($this->version->id, $schedule->version->id);
    }

    public function test_scheduler_relationship(): void
    {
        $schedule = $this->makeSchedule('pending', now()->addHour());

        $this->assertEquals($this->user->id, $schedule->scheduler->id);
    }

    public function test_publish_at_is_cast_to_datetime(): void
    {
        $publishAt = now()->addHour();
        $schedule = $this->makeSchedule('pending', $publishAt);

        $this->assertInstanceOf(\Carbon\Carbon::class, $schedule->publish_at);
    }

    public function test_default_status_is_pending(): void
    {
        $schedule = ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $this->version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->addHour(),
        ]);

        // Refresh to pick up DB default for status column
        $schedule->refresh();
        $this->assertEquals('pending', $schedule->status);
    }

    public function test_cascade_delete_when_content_deleted(): void
    {
        $this->makeSchedule('pending', now()->addHour());
        $this->assertEquals(1, ScheduledPublish::count());

        $this->content->forceDelete();

        $this->assertEquals(0, ScheduledPublish::count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeSchedule(string $status, \Carbon\Carbon $publishAt): ScheduledPublish
    {
        return ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $this->version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => $publishAt,
            'status' => $status,
        ]);
    }
}
