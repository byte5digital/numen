<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use App\Services\Migration\CmsDetectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MigrationDetectTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_detect(): void
    {
        $space = Space::factory()->create();

        $this->postJson("/api/v1/spaces/{$space->id}/migrations/detect", [
            'url' => 'https://example.com',
        ])->assertUnauthorized();
    }

    public function test_detect_validates_url(): void
    {
        $space = Space::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/spaces/{$space->id}/migrations/detect", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_detect_returns_cms_result(): void
    {
        $space = Space::factory()->create();
        $user = User::factory()->create();

        $mock = Mockery::mock(CmsDetectorService::class);
        $mock->shouldReceive('detect')
            ->once()
            ->with('https://example.com')
            ->andReturn([
                'cms' => 'wordpress',
                'version' => '6.4.2',
                'confidence' => 0.95,
            ]);

        $this->app->instance(CmsDetectorService::class, $mock);

        $this->actingAs($user)
            ->postJson("/api/v1/spaces/{$space->id}/migrations/detect", [
                'url' => 'https://example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.cms', 'wordpress')
            ->assertJsonPath('data.confidence', 0.95);
    }
}
