<?php

namespace Tests\Feature\Search;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use App\Services\Search\AskQuery;
use App\Services\Search\Results\AskResponse;
use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use App\Services\Search\SearchQuery;
use App\Services\Search\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
    }

    // ── GET /api/v1/search ────────────────────────────────────────────────────

    public function test_search_endpoint_returns_200_with_results_shape(): void
    {
        $this->mockSearchService(
            searchResult: $this->makeCollection([$this->makeResult('c1', 'Numen Guide')], 'sql')
        );

        $response = $this->getJson('/api/v1/search?q=numen&space_id='.$this->space->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'title', 'excerpt', 'url', 'content_type', 'score', 'published_at']],
            'meta' => ['total', 'page', 'per_page', 'tier_used'],
        ]);
    }

    public function test_search_endpoint_requires_query_param(): void
    {
        $response = $this->getJson('/api/v1/search');

        $response->assertUnprocessable();
    }

    public function test_search_endpoint_returns_empty_results_when_no_match(): void
    {
        $this->mockSearchService(searchResult: SearchResultCollection::empty('sql'));

        $response = $this->getJson('/api/v1/search?q=zzznomatch&space_id='.$this->space->id);

        $response->assertOk();
        $response->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    public function test_search_endpoint_passes_mode_parameter(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->expects($this->once())
            ->method('search')
            ->with($this->callback(fn (SearchQuery $q) => $q->mode === 'semantic'))
            ->willReturn(SearchResultCollection::empty('semantic'));
        $this->app->instance(SearchService::class, $mock);

        $this->getJson('/api/v1/search?q=test&mode=semantic');
    }

    public function test_search_endpoint_passes_locale_parameter(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->expects($this->once())
            ->method('search')
            ->with($this->callback(fn (SearchQuery $q) => $q->locale === 'de'))
            ->willReturn(SearchResultCollection::empty('sql'));
        $this->app->instance(SearchService::class, $mock);

        $this->getJson('/api/v1/search?q=test&locale=de');
    }

    public function test_search_endpoint_passes_content_type_filter(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->expects($this->once())
            ->method('search')
            ->with($this->callback(fn (SearchQuery $q) => $q->contentType === 'blog'))
            ->willReturn(SearchResultCollection::empty('sql'));
        $this->app->instance(SearchService::class, $mock);

        $this->getJson('/api/v1/search?q=test&type=blog');
    }

    // ── GET /api/v1/search/suggest ────────────────────────────────────────────

    public function test_suggest_endpoint_returns_suggestions(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->method('suggest')->willReturn(['Laravel', 'Lara', 'Laradock']);
        $this->app->instance(SearchService::class, $mock);

        $response = $this->getJson('/api/v1/search/suggest?q=lar&space_id='.$this->space->id);

        $response->assertOk();
        $response->assertJsonStructure(['suggestions']);
        $this->assertIsArray($response->json('suggestions'));
    }

    public function test_suggest_endpoint_requires_q_param(): void
    {
        $response = $this->getJson('/api/v1/search/suggest');

        $response->assertUnprocessable();
    }

    public function test_suggest_endpoint_respects_limit_param(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->expects($this->once())
            ->method('suggest')
            ->with($this->anything(), $this->anything(), 3)
            ->willReturn(['a', 'b', 'c']);
        $this->app->instance(SearchService::class, $mock);

        $this->getJson('/api/v1/search/suggest?q=a&limit=3');
    }

    // ── POST /api/v1/search/ask ────────────────────────────────────────────────

    public function test_ask_endpoint_returns_answer_structure(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->method('ask')->willReturn(new AskResponse(
            answer: 'Numen is an AI-First CMS.',
            sources: [['id' => 'c1', 'title' => 'About Numen', 'url' => '/content/about', 'relevance' => 0.9]],
            confidence: 0.85,
            followUpSuggestions: ['Tell me more.'],
            conversationId: 'conv-xyz',
            tierUsed: 'ask',
            tokensUsed: 200,
        ));
        $this->app->instance(SearchService::class, $mock);

        $response = $this->postJson('/api/v1/search/ask', [
            'question' => 'What is Numen?',
            'space_id' => $this->space->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'answer', 'sources', 'confidence', 'follow_ups', 'conversation_id', 'meta',
        ]);
        $this->assertSame('Numen is an AI-First CMS.', $response->json('answer'));
    }

    public function test_ask_endpoint_requires_question_param(): void
    {
        $response = $this->postJson('/api/v1/search/ask', []);

        $response->assertUnprocessable();
    }

    public function test_ask_endpoint_validates_question_min_length(): void
    {
        $response = $this->postJson('/api/v1/search/ask', ['question' => 'ab']);

        $response->assertUnprocessable();
    }

    public function test_ask_endpoint_forwards_conversation_id(): void
    {
        $mock = $this->createMock(SearchService::class);
        $mock->expects($this->once())
            ->method('ask')
            ->with($this->callback(fn (AskQuery $q) => $q->conversationId === 'conv-prev'))
            ->willReturn(AskResponse::noAnswer('test', 'conv-prev'));
        $this->app->instance(SearchService::class, $mock);

        $this->postJson('/api/v1/search/ask', [
            'question' => 'Follow-up question here?',
            'conversation_id' => 'conv-prev',
        ]);
    }

    // ── POST /api/v1/search/click ─────────────────────────────────────────────

    public function test_record_click_returns_204(): void
    {
        $response = $this->postJson('/api/v1/search/click', [
            'query' => 'laravel',
            'content_id' => 'some-content-id',
            'position' => 1,
            'space_id' => $this->space->id,
        ]);

        $response->assertNoContent();
    }

    public function test_record_click_requires_query_and_content_id(): void
    {
        $response = $this->postJson('/api/v1/search/click', []);

        $response->assertUnprocessable();
    }

    // ── Security: Unpublished Content ─────────────────────────────────────────

    public function test_search_results_never_include_unpublished_content(): void
    {
        // All tiers unavailable — SQL fallback
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
        $dv = $draft->currentVersion()->create([
            'content_id' => $draft->id,
            'version_number' => 1,
            'title' => 'Private Draft Article',
            'body' => 'Confidential content',
            'body_format' => 'html',
            'author_type' => 'user',
            'author_id' => 'system',
        ]);
        $draft->update(['current_version_id' => $dv->id]);

        // Don't mock SearchService — use real service with SQL fallback
        $response = $this->getJson('/api/v1/search?q=Private+Draft&space_id='.$this->space->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($draft->id, $ids);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mockSearchService(
        ?SearchResultCollection $searchResult = null,
        ?array $suggestions = null,
    ): void {
        $mock = $this->createMock(SearchService::class);
        $mock->method('search')->willReturn($searchResult ?? SearchResultCollection::empty('sql'));
        $mock->method('suggest')->willReturn($suggestions ?? []);
        $mock->method('ask')->willReturn(AskResponse::noAnswer('test'));
        $this->app->instance(SearchService::class, $mock);
    }

    private function makeResult(string $id, string $title): SearchResult
    {
        return new SearchResult(
            contentId: $id,
            title: $title,
            excerpt: 'An excerpt',
            url: '/content/'.$id,
            contentType: 'article',
            score: 1.0,
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
