<?php

namespace App\Services\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use App\Services\Competitor\Crawlers\CrawlerContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrawlerService
{
    /** Circuit breaker threshold: disable source after N consecutive errors */
    private const ERROR_THRESHOLD = 5;

    /** @var CrawlerContract[] */
    private array $crawlers;

    /**
     * @param  CrawlerContract[]  $crawlers
     */
    public function __construct(array $crawlers = [])
    {
        $this->crawlers = $crawlers;
    }

    /**
     * Register a crawler implementation.
     */
    public function registerCrawler(CrawlerContract $crawler): void
    {
        $this->crawlers[] = $crawler;
    }

    /**
     * Crawl a single competitor source, applying rate-limiting, robots.txt, and circuit breaker logic.
     *
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawlSource(CompetitorSource $source): Collection
    {
        // Rate limiting: skip if crawled too recently
        if ($this->isTooSoon($source)) {
            Log::info('CrawlerService: skipping source (rate limited)', ['source_id' => $source->id]);

            return collect();
        }

        // Circuit breaker: skip disabled sources
        if (! $source->is_active) {
            Log::info('CrawlerService: skipping inactive source', ['source_id' => $source->id]);

            return collect();
        }

        // Robots.txt check (only for scrape and sitemap types)
        if (in_array($source->crawler_type, ['scrape', 'sitemap'], true)) {
            if (! $this->isAllowedByRobots($source->url)) {
                Log::warning('CrawlerService: blocked by robots.txt', ['url' => $source->url]);

                return collect();
            }
        }

        $crawler = $this->resolveCrawler($source->crawler_type);
        if (! $crawler) {
            Log::error('CrawlerService: no crawler found for type', ['type' => $source->crawler_type]);

            return collect();
        }

        try {
            $items = $crawler->crawl($source);
            $items = $this->deduplicate($source, $items);

            // Reset error count on success
            $source->update([
                'last_crawled_at' => now(),
                'error_count' => 0,
            ]);

            return $items;
        } catch (\Throwable $e) {
            $this->handleCrawlError($source, $e);

            return collect();
        }
    }

    /**
     * Check if the source was crawled too recently (rate limiting).
     */
    public function isTooSoon(CompetitorSource $source): bool
    {
        if (! $source->last_crawled_at) {
            return false;
        }

        $intervalMinutes = max(1, $source->crawl_interval_minutes);

        return $source->last_crawled_at->addMinutes($intervalMinutes)->isFuture();
    }

    /**
     * Check robots.txt for the given URL.
     */
    public function isAllowedByRobots(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return true;
        }

        $base = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
        $robotsUrl = $base.'/robots.txt';

        try {
            $response = Http::timeout(10)->get($robotsUrl);

            if (! $response->successful()) {
                // If robots.txt not found, assume allowed
                return true;
            }

            return $this->parseRobotsTxt($response->body(), $url);
        } catch (\Throwable $e) {
            // If we can't fetch robots.txt, assume allowed
            Log::warning('CrawlerService: could not fetch robots.txt', ['url' => $robotsUrl, 'error' => $e->getMessage()]);

            return true;
        }
    }

    /**
     * Parse robots.txt and determine if our crawler is allowed to access the URL.
     */
    public function parseRobotsTxt(string $content, string $targetUrl): bool
    {
        $parsed = parse_url($targetUrl);
        $path = $parsed['path'] ?? '/';

        $inOurSection = false;
        $disallowedPaths = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (stripos($line, 'User-agent:') === 0) {
                $agent = trim(substr($line, strlen('User-agent:')));
                $inOurSection = ($agent === '*' || stripos($agent, 'numen') !== false);

                continue;
            }

            if ($inOurSection && stripos($line, 'Disallow:') === 0) {
                $disallowedPath = trim(substr($line, strlen('Disallow:')));
                if ($disallowedPath) {
                    $disallowedPaths[] = $disallowedPath;
                }
            }
        }

        foreach ($disallowedPaths as $disallowed) {
            if (str_starts_with($path, $disallowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deduplicate items by content_hash against existing DB records.
     *
     * @param  Collection<int, CompetitorContentItem>  $items
     * @return Collection<int, CompetitorContentItem>
     */
    public function deduplicate(CompetitorSource $source, Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $hashes = $items->pluck('content_hash')->filter()->all();
        $existingHashes = CompetitorContentItem::where('source_id', $source->id)
            ->whereIn('content_hash', $hashes)
            ->pluck('content_hash')
            ->flip()
            ->all();

        return $items->filter(fn (CompetitorContentItem $item) => ! isset($existingHashes[$item->content_hash]));
    }

    /**
     * Handle a crawl error: increment error_count and disable source if threshold exceeded.
     */
    private function handleCrawlError(CompetitorSource $source, \Throwable $e): void
    {
        Log::error('CrawlerService: crawl error', [
            'source_id' => $source->id,
            'error' => $e->getMessage(),
        ]);

        $newCount = $source->error_count + 1;
        $shouldDisable = $newCount >= self::ERROR_THRESHOLD;

        $source->update([
            'error_count' => $newCount,
            'is_active' => $shouldDisable ? false : $source->is_active,
            'last_crawled_at' => now(),
        ]);

        if ($shouldDisable) {
            Log::error('CrawlerService: source disabled due to repeated errors', [
                'source_id' => $source->id,
                'error_count' => $newCount,
            ]);
        }
    }

    /**
     * Find a crawler that supports the given type.
     */
    private function resolveCrawler(string $type): ?CrawlerContract
    {
        foreach ($this->crawlers as $crawler) {
            if ($crawler->supports($type)) {
                return $crawler;
            }
        }

        return null;
    }
}
