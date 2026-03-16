<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorSource;
use App\Services\Competitor\Crawlers\ApiCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiCrawlerTest extends TestCase
{
    private ApiCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new ApiCrawler;
    }

    public function test_supports_api_type(): void
    {
        $this->assertTrue($this->crawler->supports('api'));
        $this->assertFalse($this->crawler->supports('rss'));
    }

    public function test_fetches_and_maps_api_response(): void
    {
        Http::fake([
            'https://api.example.com/posts' => Http::response([
                ['link' => 'https://example.com/post-1', 'title' => 'Post One', 'summary' => 'Excerpt one', 'content' => 'Body one', 'created_at' => '2024-01-15T10:00:00Z'],
                ['link' => 'https://example.com/post-2', 'title' => 'Post Two', 'summary' => 'Excerpt two', 'content' => 'Body two', 'created_at' => '2024-01-16T10:00:00Z'],
            ], 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-api-01',
            'name' => 'Test API',
            'url' => 'https://api.example.com/posts',
            'crawler_type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/posts',
                'field_map' => [
                    'url' => 'link',
                    'title' => 'title',
                    'excerpt' => 'summary',
                    'body' => 'content',
                    'published_at' => 'created_at',
                ],
            ],
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertCount(2, $items);
        $this->assertEquals('Post One', $items[0]->title);
        $this->assertEquals('https://example.com/post-1', $items[0]->external_url);
        $this->assertEquals('Excerpt one', $items[0]->excerpt);
        $this->assertNotNull($items[0]->published_at);
    }

    public function test_extracts_nested_data_path(): void
    {
        Http::fake([
            'https://api.example.com/v2/posts' => Http::response([
                'data' => [
                    'items' => [
                        ['url' => 'https://example.com/nested', 'title' => 'Nested Post'],
                    ],
                ],
                'meta' => ['total' => 1],
            ], 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-api-02',
            'name' => 'Nested API',
            'url' => 'https://api.example.com/v2/posts',
            'crawler_type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/v2/posts',
                'data_path' => 'data.items',
                'field_map' => ['url' => 'url', 'title' => 'title'],
            ],
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertCount(1, $items);
        $this->assertEquals('Nested Post', $items[0]->title);
    }

    public function test_applies_bearer_auth(): void
    {
        Http::fake([
            'https://api.example.com/secure' => Http::response([
                ['url' => 'https://example.com/secure-post', 'title' => 'Secure'],
            ], 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-api-03',
            'name' => 'Secure API',
            'url' => 'https://api.example.com/secure',
            'crawler_type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/secure',
                'auth' => ['type' => 'bearer', 'token' => 'secret-token'],
                'field_map' => ['url' => 'url', 'title' => 'title'],
            ],
        ]);

        $this->crawler->crawl($source);

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_skips_items_without_url(): void
    {
        Http::fake([
            'https://api.example.com/posts' => Http::response([
                ['title' => 'No URL item'],
                ['url' => 'https://example.com/valid', 'title' => 'Valid item'],
            ], 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-api-04',
            'name' => 'Test',
            'url' => 'https://api.example.com/posts',
            'crawler_type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/posts',
                'field_map' => ['url' => 'url', 'title' => 'title'],
            ],
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertCount(1, $items);
        $this->assertEquals('Valid item', $items[0]->title);
    }

    public function test_stops_pagination_on_empty_response(): void
    {
        Http::fake([
            'https://api.example.com/posts?page=1' => Http::response([
                ['url' => 'https://example.com/p1', 'title' => 'Page 1 Post'],
            ], 200),
            'https://api.example.com/posts?page=2' => Http::response([], 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-api-05',
            'name' => 'Paged API',
            'url' => 'https://api.example.com/posts',
            'crawler_type' => 'api',
            'config' => [
                'endpoint' => 'https://api.example.com/posts',
                'field_map' => ['url' => 'url', 'title' => 'title'],
                'pagination' => ['type' => 'page', 'param' => 'page', 'max_pages' => 5],
            ],
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertCount(1, $items);
    }
}
