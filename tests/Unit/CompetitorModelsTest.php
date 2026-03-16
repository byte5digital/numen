<?php

namespace Tests\Unit;

use App\Models\CompetitorAlert;
use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use App\Models\ContentFingerprint;
use App\Models\DifferentiationAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitorModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_competitor_source_factory_creates(): void
    {
        $source = CompetitorSource::factory()->create();

        $this->assertNotNull($source->id);
        $this->assertDatabaseHas('competitor_sources', ['id' => $source->id]);
    }

    public function test_competitor_source_has_many_content_items(): void
    {
        $source = CompetitorSource::factory()->create();
        CompetitorContentItem::factory()->count(3)->create(['source_id' => $source->id]);

        $this->assertCount(3, $source->contentItems);
    }

    public function test_competitor_content_item_factory_creates(): void
    {
        $item = CompetitorContentItem::factory()->create();

        $this->assertNotNull($item->id);
        $this->assertDatabaseHas('competitor_content_items', ['id' => $item->id]);
    }

    public function test_competitor_content_item_belongs_to_source(): void
    {
        $source = CompetitorSource::factory()->create();
        $item = CompetitorContentItem::factory()->create(['source_id' => $source->id]);

        $this->assertEquals($source->id, $item->source->id);
    }

    public function test_content_fingerprint_factory_creates(): void
    {
        $fingerprint = ContentFingerprint::factory()->create();

        $this->assertNotNull($fingerprint->id);
        $this->assertDatabaseHas('content_fingerprints', ['id' => $fingerprint->id]);
    }

    public function test_content_fingerprint_morphable_relation(): void
    {
        $item = CompetitorContentItem::factory()->create();
        $fingerprint = ContentFingerprint::factory()->create([
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $item->id,
        ]);

        $this->assertEquals($item->id, $fingerprint->fingerprintable->id);
        $this->assertEquals($fingerprint->id, $item->fingerprint->id);
    }

    public function test_differentiation_analysis_factory_creates(): void
    {
        $analysis = DifferentiationAnalysis::factory()->create();

        $this->assertNotNull($analysis->id);
        $this->assertDatabaseHas('differentiation_analyses', ['id' => $analysis->id]);
    }

    public function test_differentiation_analysis_belongs_to_competitor_content(): void
    {
        $item = CompetitorContentItem::factory()->create();
        $analysis = DifferentiationAnalysis::factory()->create(['competitor_content_id' => $item->id]);

        $this->assertEquals($item->id, $analysis->competitorContent->id);
    }

    public function test_competitor_alert_factory_creates(): void
    {
        $alert = CompetitorAlert::factory()->create();

        $this->assertNotNull($alert->id);
        $this->assertDatabaseHas('competitor_alerts', ['id' => $alert->id]);
    }

    public function test_competitor_alert_has_many_events(): void
    {
        $alert = CompetitorAlert::factory()->create();
        CompetitorAlertEvent::factory()->count(2)->create(['alert_id' => $alert->id]);

        $this->assertCount(2, $alert->events);
    }

    public function test_competitor_alert_event_factory_creates(): void
    {
        $event = CompetitorAlertEvent::factory()->create();

        $this->assertNotNull($event->id);
        $this->assertDatabaseHas('competitor_alert_events', ['id' => $event->id]);
    }

    public function test_competitor_alert_event_belongs_to_alert(): void
    {
        $alert = CompetitorAlert::factory()->create();
        $event = CompetitorAlertEvent::factory()->create(['alert_id' => $alert->id]);

        $this->assertEquals($alert->id, $event->alert->id);
    }

    public function test_competitor_alert_event_belongs_to_competitor_content(): void
    {
        $item = CompetitorContentItem::factory()->create();
        $event = CompetitorAlertEvent::factory()->create(['competitor_content_id' => $item->id]);

        $this->assertEquals($item->id, $event->competitorContent->id);
    }

    public function test_competitor_source_soft_deletes(): void
    {
        $source = CompetitorSource::factory()->create();
        $source->delete();

        $this->assertSoftDeleted('competitor_sources', ['id' => $source->id]);
    }

    public function test_competitor_alert_soft_deletes(): void
    {
        $alert = CompetitorAlert::factory()->create();
        $alert->delete();

        $this->assertSoftDeleted('competitor_alerts', ['id' => $alert->id]);
    }
}
