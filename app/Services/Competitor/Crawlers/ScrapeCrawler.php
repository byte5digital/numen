<?php

namespace App\Services\Competitor\Crawlers;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTML scraper that uses configurable CSS-selector-like XPath expressions.
 *
 * Source config JSON schema:
 * {
 *   "urls": ["https://example.com/blog", ...],
 *   "selectors": {
 *     "items": "//article",          // XPath to each item block
 *     "title":   ".//h2",            // XPath relative to item
 *     "excerpt": ".//p[@class='summary']",
 *     "body":    ".//div[@class='content']",
 *     "url":     ".//a/@href",
 *     "date":    ".//time/@datetime"
 *   }
 * }
 */
class ScrapeCrawler implements CrawlerContract
{
    public function supports(string $type): bool
    {
        return $type === 'scrape';
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawl(CompetitorSource $source): Collection
    {
        $config = $source->config ?? [];
        $urls = $config['urls'] ?? [$source->url];
        $selectors = $config['selectors'] ?? [];

        $items = collect();

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(30)->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $scraped = $this->scrape($source, $url, $response->body(), $selectors);
                $items = $items->merge($scraped);
            } catch (\Throwable $e) {
                Log::warning('ScrapeCrawler: failed to scrape page', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    /**
     * Scrape a single page.
     *
     * @param  array<string, string>  $selectors
     * @return Collection<int, CompetitorContentItem>
     */
    public function scrape(CompetitorSource $source, string $pageUrl, string $html, array $selectors): Collection
    {
        $items = collect();

        libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($document);

        $itemSelector = $selectors['items'] ?? '//article';
        $nodes = $xpath->query($itemSelector);

        if ($nodes === false || $nodes->count() === 0) {
            // No item blocks found — treat whole page as single item
            $items->push($this->buildItem($source, $pageUrl, $html, $selectors, $xpath, null));

            return $items;
        }

        foreach ($nodes as $node) {
            try {
                $items->push($this->buildItem($source, $pageUrl, $html, $selectors, $xpath, $node));
            } catch (\Throwable $e) {
                Log::warning('ScrapeCrawler: failed to parse node', ['error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $selectors
     */
    private function buildItem(
        CompetitorSource $source,
        string $pageUrl,
        string $html,
        array $selectors,
        \DOMXPath $xpath,
        ?\DOMNode $context
    ): CompetitorContentItem {
        $get = function (string $key, string $default) use ($selectors, $xpath, $context): ?string {
            $selector = $selectors[$key] ?? null;
            if (! $selector) {
                return null;
            }

            $nodes = $context
                ? $xpath->query($selector, $context)
                : $xpath->query($selector);

            if ($nodes === false || $nodes->count() === 0) {
                return null;
            }

            $node = $nodes->item(0);

            return $node ? trim($node->textContent ?? $node->nodeValue ?? '') : null;
        };

        $url = $get('url', '') ?? $pageUrl;
        // Make absolute if relative
        if ($url && ! str_starts_with($url, 'http')) {
            $parsed = parse_url($pageUrl);
            $base = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
            $url = $base.'/'.ltrim($url, '/');
        }

        $title = $get('title', '');
        $excerpt = $get('excerpt', '');
        $body = $get('body', '') ?? ($context ? strip_tags($context->textContent ?? '') : null);
        $dateStr = $get('date', '');
        $publishedAt = $dateStr ? \Carbon\Carbon::parse($dateStr) : null;

        return new CompetitorContentItem([
            'source_id' => $source->id,
            'external_url' => $url ?: $pageUrl,
            'title' => $title ?: null,
            'excerpt' => $excerpt ?: null,
            'body' => $body ?: null,
            'published_at' => $publishedAt,
            'crawled_at' => now(),
            'content_hash' => md5(($url ?: $pageUrl).($title ?? '').($body ?? '')),
            'metadata' => ['source' => 'scrape'],
        ]);
    }
}
