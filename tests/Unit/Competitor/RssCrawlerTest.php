<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorSource;
use App\Services\Competitor\Crawlers\RssCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssCrawlerTest extends TestCase
{
    private RssCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new RssCrawler;
    }

    public function test_supports_rss_type(): void
    {
        $this->assertTrue($this->crawler->supports('rss'));
        $this->assertFalse($this->crawler->supports('sitemap'));
        $this->assertFalse($this->crawler->supports('api'));
    }

    public function test_parses_rss_feed(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Blog</title>
    <item>
      <title>First Post</title>
      <link>https://example.com/first-post</link>
      <description>This is the excerpt of the first post.</description>
      <pubDate>Mon, 15 Jan 2024 10:00:00 +0000</pubDate>
    </item>
    <item>
      <title>Second Post</title>
      <link>https://example.com/second-post</link>
      <description>Second excerpt here.</description>
      <pubDate>Tue, 16 Jan 2024 10:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $source = new CompetitorSource([
            'id' => 'source-01',
            'name' => 'Test',
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/feed.xml',
            'crawler_type' => 'rss',
        ]);

        $items = $this->crawler->parseXml($source, $rssXml);

        $this->assertCount(2, $items);
        $this->assertEquals('First Post', $items[0]->title);
        $this->assertEquals('https://example.com/first-post', $items[0]->external_url);
        $this->assertNotNull($items[0]->published_at);
        $this->assertNotNull($items[0]->content_hash);
    }

    public function test_parses_atom_feed(): void
    {
        $atomXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test Atom Feed</title>
  <entry>
    <title>Atom Post</title>
    <link href="https://example.com/atom-post" rel="alternate"/>
    <summary>Atom post summary.</summary>
    <content>Full content here.</content>
    <published>2024-01-15T10:00:00Z</published>
  </entry>
</feed>
XML;

        $source = new CompetitorSource([
            'id' => 'source-02',
            'name' => 'Atom Blog',
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/atom.xml',
            'crawler_type' => 'rss',
        ]);

        $items = $this->crawler->parseXml($source, $atomXml);

        $this->assertCount(1, $items);
        $this->assertEquals('Atom Post', $items[0]->title);
        $this->assertEquals('https://example.com/atom-post', $items[0]->external_url);
        $this->assertNotNull($items[0]->published_at);
    }

    public function test_skips_items_without_url(): void
    {
        $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>No URL Post</title>
      <description>Missing link.</description>
    </item>
    <item>
      <title>Valid Post</title>
      <link>https://example.com/valid</link>
    </item>
  </channel>
</rss>
XML;

        $source = new CompetitorSource([
            'id' => 'source-03',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'rss',
        ]);

        $items = $this->crawler->parseXml($source, $rssXml);

        $this->assertCount(1, $items);
        $this->assertEquals('https://example.com/valid', $items[0]->external_url);
    }

    public function test_crawl_fetches_feed_url(): void
    {
        Http::fake([
            'https://example.com/feed.xml' => Http::response($this->sampleRss(), 200),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-04',
            'name' => 'Test',
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/feed.xml',
            'crawler_type' => 'rss',
        ]);

        $items = $this->crawler->crawl($source);

        $this->assertNotEmpty($items);
        Http::assertSent(fn ($req) => $req->url() === 'https://example.com/feed.xml');
    }

    public function test_crawl_throws_on_http_error(): void
    {
        Http::fake([
            'https://example.com/feed.xml' => Http::response('', 404),
        ]);

        $source = new CompetitorSource([
            'id' => 'source-05',
            'name' => 'Test',
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/feed.xml',
            'crawler_type' => 'rss',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->crawler->crawl($source);
    }

    public function test_content_hash_is_unique_per_item(): void
    {
        $source = new CompetitorSource([
            'id' => 'source-06',
            'name' => 'Test',
            'url' => 'https://example.com',
            'crawler_type' => 'rss',
        ]);

        $items = $this->crawler->parseXml($source, $this->sampleRss());
        $hashes = $items->pluck('content_hash')->all();

        $this->assertEquals($hashes, array_unique($hashes));
    }

    private function sampleRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Post A</title>
      <link>https://example.com/a</link>
      <description>Desc A</description>
    </item>
    <item>
      <title>Post B</title>
      <link>https://example.com/b</link>
      <description>Desc B</description>
    </item>
  </channel>
</rss>
XML;
    }
}
