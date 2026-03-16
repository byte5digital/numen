<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorSource;
use App\Services\Competitor\Crawlers\ScrapeCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeCrawlerTest extends TestCase
{
    private ScrapeCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new ScrapeCrawler;
    }

    public function test_supports_scrape_type(): void
    {
        $this->assertTrue($this->crawler->supports('scrape'));
        $this->assertFalse($this->crawler->supports('rss'));
    }

    public function test_scrapes_items_with_selectors(): void
    {
        $html = <<<'HTML'
<html>
<body>
  <article>
    <h2>Article One</h2>
    <p class="summary">Summary of article one.</p>
    <a href="/article-one">Read more</a>
  </article>
  <article>
    <h2>Article Two</h2>
    <p class="summary">Summary of article two.</p>
    <a href="/article-two">Read more</a>
  </article>
</body>
</html>
HTML;

        $source = new CompetitorSource([
            'id' => 'source-scrape-01',
            'name' => 'Test',
            'url' => 'https://example.com/blog',
            'crawler_type' => 'scrape',
            'config' => [
                'urls' => ['https://example.com/blog'],
                'selectors' => [
                    'items' => '//article',
                    'title' => './/h2',
                    'excerpt' => './/p',
                    'url' => './/a/@href',
                ],
            ],
        ]);

        $items = $this->crawler->scrape($source, 'https://example.com/blog', $html, $source->config['selectors']);

        $this->assertCount(2, $items);
        $this->assertEquals('Article One', $items[0]->title);
        $this->assertEquals('Summary of article one.', $items[0]->excerpt);
    }

    public function test_falls_back_to_whole_page_when_no_items(): void
    {
        $html = '<html><head><title>Page</title></head><body><p>Some content here.</p></body></html>';

        $source = new CompetitorSource([
            'id' => 'source-scrape-02',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'scrape',
            'config' => [],
        ]);

        $items = $this->crawler->scrape($source, 'https://example.com', $html, []);

        $this->assertCount(1, $items);
        $this->assertEquals('https://example.com', $items[0]->external_url);
    }

    public function test_resolves_relative_urls_to_absolute(): void
    {
        $html = <<<'HTML'
<html>
<body>
  <article>
    <h2>Relative Link Article</h2>
    <a href="/relative-link">Read</a>
  </article>
</body>
</html>
HTML;

        $source = new CompetitorSource([
            'id' => 'source-scrape-03',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'scrape',
            'config' => [],
        ]);

        $selectors = [
            'items' => '//article',
            'url' => './/a/@href',
        ];

        $items = $this->crawler->scrape($source, 'https://example.com/blog', $html, $selectors);

        $this->assertCount(1, $items);
        $this->assertStringStartsWith('https://', $items[0]->external_url);
    }

    public function test_crawl_dispatches_http_requests(): void
    {
        Http::fake([
            'https://example.com/blog' => Http::response(
                '<html><body><article><h2>Post</h2><a href="https://example.com/post">link</a></article></body></html>',
                200
            ),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-scrape-04',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'scrape',
            'config' => [
                'urls' => ['https://example.com/blog'],
                'selectors' => ['items' => '//article', 'url' => './/a/@href'],
            ],
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertNotEmpty($items);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'example.com'));
    }

    public function test_content_hash_is_set(): void
    {
        $html = '<html><body><p>Content</p></body></html>';

        $source = new CompetitorSource([
            'id' => 'source-scrape-05',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'scrape',
            'config' => [],
        ]);

        $items = $this->crawler->scrape($source, 'https://example.com', $html, []);

        $this->assertNotNull($items->first()->content_hash);
    }
}
