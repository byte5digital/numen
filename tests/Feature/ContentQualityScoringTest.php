<?php

namespace Tests\Feature;

use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\ContentQualityScoreItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentQualityScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_content_quality_score(): void
    {
        $score = ContentQualityScore::factory()->create();

        $this->assertDatabaseHas('content_quality_scores', ['id' => $score->id]);
        $this->assertNotEmpty($score->id);
        $this->assertIsFloat($score->overall_score);
    }

    public function test_can_create_content_quality_score_item(): void
    {
        $score = ContentQualityScore::factory()->create();
        $item = ContentQualityScoreItem::factory()->create(['score_id' => $score->id]);

        $this->assertDatabaseHas('content_quality_score_items', ['id' => $item->id]);
        $this->assertEquals($score->id, $item->score_id);
        $this->assertContains($item->severity, ['info', 'warning', 'error']);
    }

    public function test_can_create_content_quality_config(): void
    {
        $config = ContentQualityConfig::factory()->create();

        $this->assertDatabaseHas('content_quality_configs', ['id' => $config->id]);
        $this->assertIsArray($config->dimension_weights);
        $this->assertIsArray($config->thresholds);
        $this->assertIsArray($config->enabled_dimensions);
        $this->assertTrue($config->auto_score_on_publish);
        $this->assertFalse($config->pipeline_gate_enabled);
        $this->assertEquals(70.0, $config->pipeline_gate_min_score);
    }

    public function test_score_has_items_relationship(): void
    {
        $score = ContentQualityScore::factory()->create();
        ContentQualityScoreItem::factory()->count(3)->create(['score_id' => $score->id]);

        $this->assertCount(3, $score->items);
    }

    public function test_score_item_belongs_to_score(): void
    {
        $score = ContentQualityScore::factory()->create();
        $item = ContentQualityScoreItem::factory()->create(['score_id' => $score->id]);

        $this->assertEquals($score->id, $item->score->id);
    }

    public function test_score_uses_ulid_primary_key(): void
    {
        $score = ContentQualityScore::factory()->create();

        // ULIDs are 26 chars
        $this->assertEquals(26, strlen($score->id));
    }

    public function test_config_uses_ulid_primary_key(): void
    {
        $config = ContentQualityConfig::factory()->create();

        $this->assertEquals(26, strlen($config->id));
    }

    public function test_score_item_uses_ulid_primary_key(): void
    {
        $item = ContentQualityScoreItem::factory()->create();

        $this->assertEquals(26, strlen($item->id));
    }

    public function test_config_with_gate_state(): void
    {
        $config = ContentQualityConfig::factory()->withGate()->create();

        $this->assertTrue($config->pipeline_gate_enabled);
        $this->assertEquals(75.0, $config->pipeline_gate_min_score);
    }

    public function test_passing_score_state(): void
    {
        $score = ContentQualityScore::factory()->passing()->create();

        $this->assertGreaterThanOrEqual(70, $score->overall_score);
    }
}
