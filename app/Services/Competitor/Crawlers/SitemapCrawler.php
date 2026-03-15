<?php

namespace App\Services\Competitor\Crawlers;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SitemapCrawler implements CrawlerContract
{
    /** Maximum pages to fetch per crawl run */
    private int $maxPages;

    public function __construct(int $maxPages = 50)
    {
        $this->maxPages = $maxPages;
    }

    public function supports(string $type): bool
    {
        return $type === 'sitemap';
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawl(CompetitorSource $source): Collection
    {
        $sitemapUrl = $source->feed_url ?? ($source->url.'/sitemap.xml');
        $urls = $this->parseSitemap($sitemapUrl);

        return $this->fetchPages($source, $urls->take($this->maxPages));
    }

    /**
     * @return Collection<int, string>
     */
    public function parseSitemap(string $url): Collection
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch sitemap from {$url}: HTTP {$response->status()}");
        }

        return $this->extractUrls($response->body());
    }

    /**
     * @return Collection<int, string>
     */
    public function extractUrls(string $xml): Collection
    {
        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);

        if ($document === false) {
            libxml_clear_errors();

            throw new \RuntimeException('Failed to parse sitemap XML');
        }

        $urls = collect();
        $name = $document->getName();

        // Sitemap index — recurse into child sitemaps (one level deep)
        if ($name === 'sitemapindex') {
            foreach ($document->sitemap as $sitemap) {
                $childUrl = (string) ($sitemap->loc ?? '');
                if ($childUrl) {
                    try {
                        $childResponse = Http::timeout(30)->get($childUrl);
                        if ($childResponse->successful()) {
                            $urls = $urls->merge($this->extractUrls($childResponse->body()));
                        }
                    } catch (\Throwable $e) {
                        Log::warning('SitemapCrawler: failed to fetch child sitemap', ['url' => $childUrl, 'error' => $e->getMessage()]);
                    }
                }
            }
        } else {
            // Regular sitemap
            foreach ($document->url as $urlEntry) {
                $loc = (string) ($urlEntry->loc ?? '');
                if ($loc) {
                    $urls->push($loc);
                }
            }
        }

        return $urls;
    }

    /**
     * @param  Collection<int, string>  $urls
     * @return Collection<int, CompetitorContentItem>
     */
    private function fetchPages(CompetitorSource $source, Collection $urls): Collection
    {
        $items = collect();

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(30)->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $html = $response->body();
                [$title, $excerpt, $body] = $this->extractContent($html);

                $item = new CompetitorContentItem([
                    'source_id' => $source->id,
                    'external_url' => $url,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'published_at' => null,
                    'crawled_at' => now(),
                    'content_hash' => md5($url.$body),
                    'metadata' => ['source' => 'sitemap'],
                ]);

                $items->push($item);
            } catch (\Throwable $e) {
                Log::warning('SitemapCrawler: failed to fetch page', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    /**
     * Extract title, excerpt, and body from HTML.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    public function extractContent(string $html): array
    {
        // Extract title
        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
        }

        // Strip scripts/styles
        $cleaned = preg_replace('/<(script|style|nav|header|footer|aside)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $text = strip_tags($cleaned);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        $excerpt = mb_strlen($text) > 300 ? mb_substr($text, 0, 300).'...' : ($text ?: null);
        $body = $text ?: null;

        return [$title ?: null, $excerpt, $body];
    }
}
