<?php

namespace Tests\Unit\Performance;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use App\Services\Performance\ABTestService;
use App\Services\Performance\StatisticalSignificanceCalculator;
use App\Services\Performance\TrafficSplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ABTestServiceTest extends TestCase
{
    use RefreshDatabase;

    private ABTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ABTestService(
            new TrafficSplitter,
            new StatisticalSignificanceCalculator,
        );
    }

    public function test_create_test_with_variants(): void
    {
        $spaceId = strtoupper(Str::ulid());
        $contentId1 = strtoupper(Str::ulid());
        $contentId2 = strtoupper(Str::ulid());

        $test = $this->service->createTest($spaceId, [
            'name' => 'Headline Test',
            'hypothesis' => 'Shorter headlines convert better',
            'metric' => 'conversion_rate',
            'variants' => [
                ['content_id' => $contentId1, 'label' => 'Control', 'is_control' => true],
                ['content_id' => $contentId2, 'label' => 'Short Headline', 'is_control' => false],
            ],
        ]);

        $this->assertInstanceOf(ContentAbTest::class, $test);
        $this->assertEquals('draft', $test->status);
        $this->assertCount(2, $test->variants);
        $this->assertTrue($test->variants->first()->is_control);
    }

    public function test_get_active_test(): void
    {
        $spaceId = strtoupper(Str::ulid());

        ContentAbTest::factory()->create([
            'space_id' => $spaceId,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $active = $this->service->getActiveTest($spaceId);

        $this->assertNotNull($active);
        $this->assertEquals('running', $active->status);
    }

    public function test_get_active_test_returns_null_when_none(): void
    {
        $this->assertNull($this->service->getActiveTest(strtoupper(Str::ulid())));
    }

    public function test_assign_variant_starts_draft_test(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'draft']);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false]);

        $variant = $this->service->assignVariant($test, 'visitor-1');

        $this->assertInstanceOf(ContentAbVariant::class, $variant);
        $this->assertEquals('running', $test->fresh()->status);
    }

    public function test_assign_variant_increments_view_count(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        $control = ContentAbVariant::factory()->control()->create(['test_id' => $test->id, 'view_count' => 0]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false, 'view_count' => 0]);

        // Assign multiple visitors
        for ($i = 0; $i < 10; $i++) {
            $this->service->assignVariant($test, 'visitor-'.$i);
        }

        $totalViews = $test->variants()->sum('view_count');
        $this->assertEquals(10, $totalViews);
    }

    public function test_record_conversion(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        $variant = ContentAbVariant::factory()->create([
            'test_id' => $test->id,
            'view_count' => 100,
            'conversion_rate' => 0.05,
        ]);

        $this->service->recordConversion($test, $variant->id, 'visitor-1');

        $variant->refresh();
        $this->assertGreaterThan(0.05, (float) $variant->conversion_rate);
    }

    public function test_end_test(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running', 'started_at' => now()->subDay()]);
        ContentAbVariant::factory()->control()->create([
            'test_id' => $test->id,
            'view_count' => 100,
            'conversion_rate' => 0.05,
        ]);
        ContentAbVariant::factory()->create([
            'test_id' => $test->id,
            'is_control' => false,
            'view_count' => 100,
            'conversion_rate' => 0.08,
        ]);

        $results = $this->service->endTest($test);

        $test->refresh();
        $this->assertEquals('completed', $test->status);
        $this->assertNotNull($test->ended_at);
        $this->assertArrayHasKey('conclusion', $results);
    }

    public function test_get_results(): void
    {
        $test = ContentAbTest::factory()->create(['status' => 'running']);
        ContentAbVariant::factory()->control()->create([
            'test_id' => $test->id,
            'view_count' => 500,
            'conversion_rate' => 0.05,
        ]);
        ContentAbVariant::factory()->create([
            'test_id' => $test->id,
            'is_control' => false,
            'view_count' => 500,
            'conversion_rate' => 0.08,
        ]);

        $results = $this->service->getResults($test);

        $this->assertArrayHasKey('test_id', $results);
        $this->assertArrayHasKey('variants', $results);
        $this->assertArrayHasKey('significance', $results);
        $this->assertArrayHasKey('is_significant', $results);
        $this->assertArrayHasKey('total_visitors', $results);
        $this->assertCount(2, $results['variants']);
    }

    public function test_factory_smoke(): void
    {
        $test = ContentAbTest::factory()->create();
        $this->assertNotNull($test->id);

        $variant = ContentAbVariant::factory()->create(['test_id' => $test->id]);
        $this->assertNotNull($variant->id);
    }
}
