<?php

namespace Tests\Unit\Performance;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use App\Services\Performance\TrafficSplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficSplitterTest extends TestCase
{
    use RefreshDatabase;

    private TrafficSplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->splitter = new TrafficSplitter;
    }

    public function test_deterministic_assignment(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false, 'label' => 'Variant A']);

        $visitorId = 'visitor-123';
        $first = $this->splitter->split($test, $visitorId);
        $second = $this->splitter->split($test, $visitorId);

        $this->assertEquals($first->id, $second->id);
    }

    public function test_distributes_across_variants(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        $control = ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        $variant = ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false, 'label' => 'Variant A']);

        $assignments = ['control' => 0, 'variant' => 0];
        for ($i = 0; $i < 200; $i++) {
            $assigned = $this->splitter->split($test, 'visitor-'.$i);
            if ($assigned->id === $control->id) {
                $assignments['control']++;
            } else {
                $assignments['variant']++;
            }
        }

        // Both should get some visitors (not all to one side)
        $this->assertGreaterThan(20, $assignments['control']);
        $this->assertGreaterThan(20, $assignments['variant']);
    }

    public function test_throws_on_empty_variants(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);

        $this->expectException(\RuntimeException::class);
        $this->splitter->split($test, 'visitor-1');
    }

    public function test_adjust_weights_validates_sum(): void
    {
        $test = ContentAbTest::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->splitter->adjustWeights($test, ['id1' => 0.3, 'id2' => 0.3]);
    }

    public function test_adjust_weights_updates_variants(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        $control = ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        $variant = ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false]);

        $this->splitter->adjustWeights($test, [
            $control->id => 0.8,
            $variant->id => 0.2,
        ]);

        $this->assertEquals('0.8000', $control->fresh()->weight);
        $this->assertEquals('0.2000', $variant->fresh()->weight);
    }
}
