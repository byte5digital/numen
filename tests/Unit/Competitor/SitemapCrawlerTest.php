<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorSource;
use App\Services\Competitor\Crawlers\SitemapCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapCrawlerTest extends TestCase
{
    private SitemapCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new SitemapCrawler(maxPages: 10);
    }

    public function test_supports_sitemap_type(): void
    {
        $this->assertTrue($this->crawler->supports('sitemap'));
        $this->assertFalse($this->crawler->supports('rss'));
    }

    public function test_extracts_urls_from_sitemap(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://example.com/page-1</loc></url>
  <url><loc>https://example.com/page-2</loc></url>
  <url><loc>https://example.com/page-3</loc></url>
</urlset>
XML;

        $urls = $this->crawler->extractUrls($xml);

        $this->assertCount(3, $urls);
        $this->assertTrue($urls->contains('https://example.com/page-1'));
        $this->assertTrue($urls->contains('https://example.com/page-3'));
    }

    public function test_extracts_urls_from_sitemap_index(): void
    {
        $indexXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap><loc>https://example.com/sitemap-1.xml</loc></sitemap>
</sitemapindex>
XML;

        $childXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://example.com/child-page</loc></url>
</urlset>
XML;

        Http::fake([
            'https://example.com/sitemap-1.xml' => Http::response($childXml, 200),
        ]);

        $urls = $this->crawler->extractUrls($indexXml);

        $this->assertTrue($urls->contains('https://example.com/child-page'));
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->crawler->extractUrls('not valid xml at all!!!');
    }

    public function test_extracts_content_from_html(): void
    {
        $html = <<<'HTML'
<html>
<head><title>Test Page Title</title></head>
<body>
  <nav>Navigation junk</nav>
  <h1>Article Heading</h1>
  <p>This is the main body text of the article. It contains useful information.</p>
</body>
</html>
HTML;

        [$title, $excerpt, $body] = $this->crawler->extractContent($html);

        $this->assertEquals('Test Page Title', $title);
        $this->assertNotNull($excerpt);
        $this->assertNotNull($body);
        $this->assertStringContainsString('main body text', $body);
    }

    public function test_crawl_fetches_sitemap_and_pages(): void
    {
        $sitemapXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://example.com/article-1</loc></url>
</urlset>
XML;

        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($sitemapXml, 200),
            'https://example.com/article-1' => Http::response('<html><head><title>Article 1</title></head><body><p>Article content.</p></body></html>', 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-sitemap-01',
            'name' => 'Test',
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/sitemap.xml',
            'crawler_type' => 'sitemap',
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertNotEmpty($items);
        $this->assertEquals('https://example.com/article-1', $items->first()->external_url);
    }
}
