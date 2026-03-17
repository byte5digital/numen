<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\MappingPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MigrationMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    private MigrationSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
        $this->session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
            'schema_snapshot' => [
                ['key' => 'post', 'label' => 'Post', 'fields' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true],
                    ['name' => 'content', 'type' => 'richtext', 'required' => false],
                ]],
            ],
        ]);
    }

    public function test_can_list_mappings(): void
    {
        MigrationTypeMapping::factory()->count(2)->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/mappings")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_save_mappings(): void
    {
        $payload = [
            'mappings' => [
                [
                    'source_type_key' => 'post',
                    'source_type_label' => 'Post',
                    'numen_type_slug' => 'article',
                    'field_map' => ['title' => 'title', 'content' => 'body'],
                    'status' => 'approved',
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->putJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/mappings", $payload)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source_type_key', 'post');

        $this->assertDatabaseHas('migration_type_mappings', [
            'migration_session_id' => $this->session->id,
            'source_type_key' => 'post',
            'status' => 'approved',
        ]);
    }

    public function test_save_mappings_validates_input(): void
    {
        $this->actingAs($this->user)
            ->putJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/mappings", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mappings']);
    }

    public function test_suggest_returns_422_without_schema(): void
    {
        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
            'schema_snapshot' => null,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}/mappings/suggest")
            ->assertUnprocessable();
    }

    public function test_suggest_returns_preview_data(): void
    {
        $mockPreview = [
            'comparison' => ['matched_types' => [], 'unmatched_source' => [], 'unmatched_numen' => []],
            'type_mappings' => [],
            'summary' => ['total_source_types' => 1, 'mapped_types' => 0, 'total_fields' => 2, 'mapped_fields' => 0, 'avg_confidence' => 0.0],
        ];

        $mock = Mockery::mock(MappingPreviewService::class);
        $mock->shouldReceive('generatePreview')
            ->once()
            ->andReturn($mockPreview);

        $this->app->instance(MappingPreviewService::class, $mock);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/mappings/suggest")
            ->assertOk()
            ->assertJsonStructure(['data' => ['comparison', 'type_mappings', 'summary']]);
    }

    public function test_preview_returns_mapping_results(): void
    {
        $mockPreview = [
            'comparison' => ['matched_types' => [], 'unmatched_source' => [], 'unmatched_numen' => []],
            'type_mappings' => [],
            'summary' => ['total_source_types' => 1, 'mapped_types' => 0, 'total_fields' => 2, 'mapped_fields' => 0, 'avg_confidence' => 0.0],
        ];

        $mock = Mockery::mock(MappingPreviewService::class);
        $mock->shouldReceive('generatePreview')
            ->once()
            ->andReturn($mockPreview);

        $this->app->instance(MappingPreviewService::class, $mock);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/mappings/preview")
            ->assertOk()
            ->assertJsonStructure(['data' => ['comparison', 'type_mappings', 'summary']]);
    }

    public function test_mapping_factory_smoke(): void
    {
        $mapping = MigrationTypeMapping::factory()->create();
        $this->assertNotNull($mapping->id);
        $this->assertEquals(26, strlen($mapping->id));
    }
}
