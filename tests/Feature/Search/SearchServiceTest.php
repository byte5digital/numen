<?php

namespace Tests\Feature\Search;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\PromotedResult;
use App\Models\Space;
use App\Services\Search\AskQuery;
use App\Services\Search\ConversationalDriver;
use App\Services\Search\InstantSearchDriver;
use App\Services\Search\PromotedResultsService;
use App\Services\Search\Results\AskResponse;
use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use App\Services\Search\SearchAnalyticsRecorder;
use App\Services\Search\SearchCapabilities;
use App\Services\Search\SearchCapabilityDetector;
use App\Services\Search\SearchQuery;
use App\Services\Search\SearchRanker;
use App\Services\Search\SearchService;
use App\Services\Search\SemanticSearchDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
    }

    // ── SQL Fallback ──────────────────────────────────────────────────────────

    public function test_sql_fallback_returns_published_content_matching_query(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);
        $version = $content->currentVersion()->create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Laravel Testing Guide',
            'body' => 'Comprehensive guide to testing in Laravel.',
            'body_format' => 'html',
            'author_type' => 'user',
            'author_id' => 'system',
        ]);
        $content->update(['current_version_id' => $version->id]);

        // All tiers unavailable → SQL fallback
        $service = $this->makeService(allUnavailable: true);
        $query = new SearchQuery(
            query: 'Laravel',
            spaceId: $this->space->id,
        );

        $results = $service->search($query);

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertSame('sql', $results->tierUsed());
    }

    public function test_sql_fallback_excludes_draft_content(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        $draft = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
        $draft->currentVersion()->create([
            'content_id' => $draft->id,
            'version_number' => 1,
            'title' => 'Secret Draft Post',
            'body' => 'Secret content',
            'body_format' => 'html',
            'author_type' => 'user',
            'author_id' => 'system',
        ]);

        $service = $this->makeService(allUnavailable: true);
        $query = new SearchQuery(
            query: 'Secret Draft',
            spaceId: $this->space->id,
        );

        $results = $service->search($query);

        $ids = array_map(fn (SearchResult $r) => $r->contentId, $results->items());
        $this->assertNotContains($draft->id, $ids);
    }

    // ── Instant Mode ──────────────────────────────────────────────────────────

    public function test_search_instant_mode_uses_instant_driver(): void
    {
        $expectedResults = $this->makeCollection([
            $this->makeResult('c1', 'Result'),
        ], 'instant');

        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->once()->andReturn($expectedResults);
        $instant->shouldReceive('suggest')->andReturn([]);

        $service = $this->makeService(
            instant: $instant,
            caps: new SearchCapabilities(instant: true, semantic: false, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'instant');
        $results = $service->search($query);

        $this->assertSame('instant', $results->tierUsed());
    }

    public function test_search_instant_mode_falls_back_to_sql_when_instant_unavailable(): void
    {
        $service = $this->makeService(
            caps: new SearchCapabilities(instant: false, semantic: false, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'instant');
        $results = $service->search($query);

        $this->assertSame('sql', $results->tierUsed());
    }

    public function test_search_instant_mode_falls_back_to_sql_when_instant_throws(): void
    {
        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->andThrow(new \RuntimeException('Meilisearch down'));

        $service = $this->makeService(
            instant: $instant,
            caps: new SearchCapabilities(instant: true, semantic: false, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'instant');
        $results = $service->search($query);

        $this->assertSame('sql', $results->tierUsed());
    }

    // ── Semantic Mode ────────────────────────────────────────────────────────

    public function test_search_semantic_mode_uses_semantic_driver(): void
    {
        $expectedResults = $this->makeCollection([
            $this->makeResult('c1', 'Semantic Match'),
        ], 'semantic');

        $semantic = Mockery::mock(SemanticSearchDriver::class);
        $semantic->shouldReceive('search')->once()->andReturn($expectedResults);

        $service = $this->makeService(
            semantic: $semantic,
            caps: new SearchCapabilities(instant: false, semantic: true, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'semantic');
        $results = $service->search($query);

        $this->assertSame('semantic', $results->tierUsed());
    }

    public function test_search_semantic_mode_falls_back_through_instant_then_sql(): void
    {
        $semantic = Mockery::mock(SemanticSearchDriver::class);
        $semantic->shouldReceive('search')->andThrow(new \RuntimeException('pgvector down'));

        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->andThrow(new \RuntimeException('Meilisearch down'));

        $service = $this->makeService(
            instant: $instant,
            semantic: $semantic,
            caps: new SearchCapabilities(instant: true, semantic: true, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'semantic');
        $results = $service->search($query);

        $this->assertSame('sql', $results->tierUsed());
    }

    // ── Hybrid Mode ──────────────────────────────────────────────────────────

    public function test_search_hybrid_mode_merges_both_tiers(): void
    {
        $instantResults = $this->makeCollection([$this->makeResult('c1', 'Keyword')], 'instant');
        $semanticResults = $this->makeCollection([$this->makeResult('c2', 'Semantic')], 'semantic');

        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->andReturn($instantResults);

        $semantic = Mockery::mock(SemanticSearchDriver::class);
        $semantic->shouldReceive('search')->andReturn($semanticResults);

        $service = $this->makeService(
            instant: $instant,
            semantic: $semantic,
            caps: new SearchCapabilities(instant: true, semantic: true, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'hybrid');
        $results = $service->search($query);

        $this->assertSame('hybrid', $results->tierUsed());
        $this->assertCount(2, $results->items());
    }

    public function test_hybrid_uses_only_instant_when_semantic_unavailable(): void
    {
        $instantResults = $this->makeCollection([$this->makeResult('c1', 'Keyword')], 'instant');

        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->andReturn($instantResults);

        $service = $this->makeService(
            instant: $instant,
            caps: new SearchCapabilities(instant: true, semantic: false, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'hybrid');
        $results = $service->search($query);

        $this->assertSame('instant', $results->tierUsed());
    }

    public function test_hybrid_uses_only_semantic_when_instant_unavailable(): void
    {
        $semanticResults = $this->makeCollection([$this->makeResult('c2', 'Semantic')], 'semantic');

        $semantic = Mockery::mock(SemanticSearchDriver::class);
        $semantic->shouldReceive('search')->andReturn($semanticResults);

        $service = $this->makeService(
            semantic: $semantic,
            caps: new SearchCapabilities(instant: false, semantic: true, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'hybrid');
        $results = $service->search($query);

        $this->assertSame('semantic', $results->tierUsed());
    }

    public function test_hybrid_falls_back_to_sql_when_all_fail(): void
    {
        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('search')->andThrow(new \RuntimeException('down'));

        $semantic = Mockery::mock(SemanticSearchDriver::class);
        $semantic->shouldReceive('search')->andThrow(new \RuntimeException('down'));

        $service = $this->makeService(
            instant: $instant,
            semantic: $semantic,
            caps: new SearchCapabilities(instant: true, semantic: true, ask: false)
        );

        $query = new SearchQuery(query: 'test', spaceId: $this->space->id, mode: 'hybrid');
        $results = $service->search($query);

        $this->assertSame('sql', $results->tierUsed());
    }

    // ── Suggest ───────────────────────────────────────────────────────────────

    public function test_suggest_uses_instant_driver_when_available(): void
    {
        $instant = Mockery::mock(InstantSearchDriver::class);
        $instant->shouldReceive('suggest')->with('lar', $this->space->id, 5)->once()->andReturn(['Laravel', 'Lara']);

        $service = $this->makeService(
            instant: $instant,
            caps: new SearchCapabilities(instant: true, semantic: false, ask: false)
        );

        $suggestions = $service->suggest('lar', $this->space->id, 5);

        $this->assertSame(['Laravel', 'Lara'], $suggestions);
    }

    public function test_suggest_falls_back_to_sql_when_instant_unavailable(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);
        $version = $content->currentVersion()->create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => 'Laravel Testing',
            'body' => 'Body text',
            'body_format' => 'html',
            'author_type' => 'user',
            'author_id' => 'system',
        ]);
        $content->update(['current_version_id' => $version->id]);

        $service = $this->makeService(
            caps: new SearchCapabilities(instant: false, semantic: false, ask: false)
        );

        $suggestions = $service->suggest('Laravel', $this->space->id, 5);

        $this->assertIsArray($suggestions);
        $this->assertNotEmpty($suggestions);
        $this->assertContains('Laravel Testing', $suggestions);
    }

    // ── Ask (RAG) ─────────────────────────────────────────────────────────────

    public function test_ask_returns_no_answer_when_ask_tier_unavailable(): void
    {
        $service = $this->makeService(
            caps: new SearchCapabilities(instant: false, semantic: false, ask: false)
        );

        $query = new AskQuery(question: 'What is Numen?', spaceId: $this->space->id);
        $response = $service->ask($query);

        $this->assertStringContainsString("don't have enough information", $response->answer);
    }

    // ── Promoted Results ──────────────────────────────────────────────────────

    public function test_promoted_results_injected_at_top_of_results(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        $promoted = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);
        $pVersion = $promoted->currentVersion()->create([
            'content_id' => $promoted->id,
            'version_number' => 1,
            'title' => 'Promoted Content',
            'body' => 'Body',
            'body_format' => 'html',
            'author_type' => 'user',
            'author_id' => 'system',
        ]);
        $promoted->update(['current_version_id' => $pVersion->id]);

        PromotedResult::create([
            'space_id' => $this->space->id,
            'query' => 'test',
            'content_id' => $promoted->id,
            'position' => 1,
        ]);

        $service = $this->makeService(allUnavailable: true);
        $query = new SearchQuery(query: 'test', spaceId: $this->space->id);
        $results = $service->search($query);

        $firstItem = $results->items()[0] ?? null;
        $this->assertNotNull($firstItem);
        $this->assertSame($promoted->id, $firstItem->contentId);
        $this->assertSame(9999.0, $firstItem->score);
    }

    // ── Security: Unpublished Content ─────────────────────────────────────────

    public function test_unpublished_content_never_appears_in_sql_fallback_results(): void
    {
        $contentType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Article',
            'slug' => 'article',
            'schema' => ['fields' => []],
        ]);

        // Create various non-published statuses
        foreach (['draft', 'archived', 'scheduled'] as $status) {
            $c = Content::factory()->create([
                'space_id' => $this->space->id,
                'content_type_id' => $contentType->id,
                'status' => $status,
                'published_at' => null,
            ]);
            $c->currentVersion()->create([
                'content_id' => $c->id,
                'version_number' => 1,
                'title' => "Secret {$status} Post",
                'body' => "Secret {$status}",
                'body_format' => 'html',
                'author_type' => 'user',
                'author_id' => 'system',
            ]);
        }

        $service = $this->makeService(allUnavailable: true);
        $query = new SearchQuery(query: 'Secret', spaceId: $this->space->id);
        $results = $service->search($query);

        $this->assertCount(0, $results->items());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeService(
        bool $allUnavailable = false,
        ?InstantSearchDriver $instant = null,
        ?SemanticSearchDriver $semantic = null,
        ?ConversationalDriver $conversational = null,
        ?SearchCapabilities $caps = null,
    ): SearchService {
        if ($allUnavailable) {
            $caps = new SearchCapabilities(instant: false, semantic: false, ask: false);
        }
        $caps ??= new SearchCapabilities(instant: false, semantic: false, ask: false);

        if ($instant === null) {
            $instant = Mockery::mock(InstantSearchDriver::class);
            $instant->shouldReceive('search')->andReturn(SearchResultCollection::empty('instant'));
            $instant->shouldReceive('suggest')->andReturn([]);
        }

        if ($semantic === null) {
            $semantic = Mockery::mock(SemanticSearchDriver::class);
            $semantic->shouldReceive('search')->andReturn(SearchResultCollection::empty('semantic'));
        }

        if ($conversational === null) {
            $conversational = Mockery::mock(ConversationalDriver::class);
            $conversational->shouldReceive('ask')->andReturn(AskResponse::noAnswer('test'));
        }

        $capDetector = Mockery::mock(SearchCapabilityDetector::class);
        $capDetector->shouldReceive('detect')->andReturn($caps);

        $analytics = Mockery::mock(SearchAnalyticsRecorder::class);
        $analytics->shouldReceive('record')->andReturn(null);
        $analytics->shouldReceive('recordClick')->andReturn(null);

        return new SearchService(
            instant: $instant,
            semantic: $semantic,
            conversational: $conversational,
            ranker: new SearchRanker,
            promoted: new PromotedResultsService,
            analytics: $analytics,
            capabilities: $capDetector,
        );
    }

    private function makeResult(string $id, string $title, float $score = 1.0): SearchResult
    {
        return new SearchResult(
            contentId: $id,
            title: $title,
            excerpt: '',
            url: '/content/'.$id,
            contentType: 'article',
            score: $score,
            publishedAt: '2026-01-01T00:00:00Z',
        );
    }

    /** @param SearchResult[] $items */
    private function makeCollection(array $items, string $tier): SearchResultCollection
    {
        return new SearchResultCollection(
            items: $items,
            total: count($items),
            page: 1,
            perPage: 20,
            tierUsed: $tier,
        );
    }
}
