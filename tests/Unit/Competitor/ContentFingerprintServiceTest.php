<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentFingerprint;
use App\Services\Competitor\ContentFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentFingerprintServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentFingerprintService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentFingerprintService(null);
    }

    public function test_fingerprint_extracts_topics_from_title(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'Machine Learning: Deep Learning vs Traditional AI',
            'body' => 'Machine learning is a subset of artificial intelligence. Deep learning uses neural networks.',
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertInstanceOf(ContentFingerprint::class, $fp);
        $this->assertNotEmpty($fp->topics);
        $this->assertContains('Machine Learning', $fp->topics);
    }

    public function test_fingerprint_extracts_keywords_with_tf_scores(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'Artificial Intelligence Overview',
            'body' => 'Artificial intelligence and machine learning are transforming technology. Machine learning algorithms are used in many applications. Intelligence systems improve over time.',
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertNotEmpty($fp->keywords);
        $this->assertIsArray($fp->keywords);

        foreach ($fp->keywords as $term => $score) {
            $this->assertIsString($term);
            $this->assertIsFloat($score);
            $this->assertGreaterThan(0.0, $score);
        }
    }

    public function test_fingerprint_extracts_entities_from_proper_nouns(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'How Google and Microsoft compete in AI',
            'body' => 'Google DeepMind and Microsoft Azure are major players. OpenAI is another key competitor.',
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertIsArray($fp->entities);
        $multiWordEntities = array_filter($fp->entities, fn ($e) => str_contains($e, ' '));
        $this->assertNotEmpty($multiWordEntities);
    }

    public function test_fingerprint_persists_to_database(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'Test Article',
            'body' => 'This is a test article body with some content.',
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertDatabaseHas('content_fingerprints', [
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $item->id,
        ]);

        $this->assertNotNull($fp->fingerprinted_at);
    }

    public function test_fingerprint_updates_existing_record(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'Original Title',
            'body' => 'Original body text.',
        ]);

        $fp1 = $this->service->fingerprint($item);

        $item->title = 'Updated Title: New Content';
        $item->save();

        $fp2 = $this->service->fingerprint($item);

        $this->assertEquals($fp1->id, $fp2->id);
        $this->assertDatabaseCount('content_fingerprints', 1);
    }

    public function test_fingerprint_handles_empty_content_gracefully(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => null,
            'body' => null,
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertInstanceOf(ContentFingerprint::class, $fp);
        $this->assertIsArray($fp->topics);
        $this->assertIsArray($fp->entities);
        $this->assertIsArray($fp->keywords);
    }

    public function test_fingerprint_excludes_stopwords_from_keywords(): void
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'The Great Technology Revolution',
            'body' => 'The technology sector is growing. The revolution is happening now. Technology will change everything.',
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertArrayNotHasKey('the', $fp->keywords);
        $this->assertArrayNotHasKey('is', $fp->keywords);
        $this->assertArrayNotHasKey('and', $fp->keywords);
    }

    public function test_fingerprint_limits_keywords_to_top_n(): void
    {
        $loremBody = str_repeat('Lorem ipsum dolor sit amet consectetur adipiscing elit sed eiusmod tempor incididunt labore magna aliqua enim minim veniam nostrud exercitation ullamco laboris nisi aliquip commodo consequat duis aute irure reprehenderit voluptate velit esse cillum fugiat nulla pariatur excepteur sint occaecat cupidatat proident culpa officia deserunt mollit anim est laborum ', 5);

        $item = CompetitorContentItem::factory()->create([
            'title' => 'Long Article',
            'body' => $loremBody,
        ]);

        $fp = $this->service->fingerprint($item);

        $this->assertLessThanOrEqual(20, count($fp->keywords));
    }
}
