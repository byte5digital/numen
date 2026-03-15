<?php

namespace Tests\Unit;

use App\Exceptions\PermissionDeniedException;
use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Models\User;
use App\Pipelines\PipelineExecutor;
use App\Services\Chat\IntentPermissionGuard;
use App\Services\Chat\IntentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class IntentRouterTest extends TestCase
{
    use RefreshDatabase;

    private IntentRouter $router;

    private User $adminUser;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var PipelineExecutor&MockInterface $mockExecutor */
        $mockExecutor = $this->createMock(PipelineExecutor::class);
        $this->router = new IntentRouter($mockExecutor);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->space = Space::factory()->create();
    }

    /** @test */
    public function test_routes_content_query_intent(): void
    {
        $intent = [
            'action' => 'content.query',
            'params' => ['status' => 'published'],
        ];

        $result = $this->router->route($intent, $this->adminUser, $this->space);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('content item(s) found', $result['message']);
    }

    /** @test */
    public function test_routes_content_create_intent(): void
    {
        $intent = [
            'action' => 'content.create',
            'params' => [
                'title' => 'My New Article',
                'type' => 'blog_post',
            ],
        ];

        $result = $this->router->route($intent, $this->adminUser, $this->space);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('brief_id', $result['result']);
    }

    /** @test */
    public function test_routes_content_delete_requires_confirmation(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $intent = [
            'action' => 'content.delete',
            'params' => ['content_id' => $content->id],
        ];

        $result = $this->router->route($intent, $this->adminUser, $this->space);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString($content->id, $result['message']);

        // Content uses SoftDeletes — verify it was soft-deleted
        $this->assertSoftDeleted('contents', ['id' => $content->id]);
    }

    /** @test */
    public function test_routes_pipeline_trigger_intent(): void
    {
        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
        ]);

        $fakeRun = new PipelineRun;
        $fakeRun->id = 'run-ulid-01kks001';
        $fakeRun->pipeline_id = $pipeline->id ?? 'fake-pipeline-id';
        $fakeRun->status = 'running';

        /** @var PipelineExecutor&MockInterface $mockExecutor */
        $mockExecutor = $this->createMock(PipelineExecutor::class);
        $mockExecutor->expects($this->once())
            ->method('start')
            ->willReturn($fakeRun);

        $router = new IntentRouter($mockExecutor);

        $intent = [
            'action' => 'pipeline.trigger',
            'params' => ['brief_id' => $brief->id],
        ];

        $result = $router->route($intent, $this->adminUser, $this->space);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString($fakeRun->id, $result['message']);
    }

    /** @test */
    public function test_routes_generic_query_intent(): void
    {
        $intent = ['action' => 'query.generic', 'params' => []];

        $result = $this->router->route($intent, $this->adminUser, $this->space);

        $this->assertTrue($result['success']);
        $this->assertEquals('No action required.', $result['message']);
    }

    /** @test */
    public function test_permission_guard_blocks_unauthorized(): void
    {
        $guard = new IntentPermissionGuard;

        // Regular user with no roles/permissions
        $user = User::factory()->create(['role' => 'editor']);

        $intent = ['action' => 'content.delete', 'params' => []];

        $this->expectException(PermissionDeniedException::class);

        $guard->check($intent, $user, $this->space);
    }
}
