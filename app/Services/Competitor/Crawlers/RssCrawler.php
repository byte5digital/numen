<?php

namespace App\Services\Competitor\Crawlers;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssCrawler implements CrawlerContract
{
    public function supports(string $type): bool
    {
        return $type === 'rss';
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawl(CompetitorSource $source): Collection
    {
        $feedUrl = $source->feed_url ?? $source->url;

        $response = Http::timeout(30)->get($feedUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch RSS feed from {$feedUrl}: HTTP {$response->status()}");
        }

        return $this->parseXml($source, $response->body());
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    public function parseXml(CompetitorSource $source, string $xml): Collection
    {
        $items = collect();

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);

        if ($document === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException('Failed to parse RSS/Atom XML: '.($errors[0]->message ?? 'unknown error'));
        }

        // Detect Atom vs RSS
        $namespaces = $document->getNamespaces(true);
        $isAtom = isset($namespaces['']) && str_contains((string) $document->getName(), 'feed')
            || $document->getName() === 'feed';

        if ($isAtom) {
            $items = $this->parseAtom($source, $document);
        } else {
            $items = $this->parseRss($source, $document);
        }

        return $items;
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    private function parseRss(CompetitorSource $source, \SimpleXMLElement $document): Collection
    {
        $items = collect();
        $channel = $document->channel ?? $document;
        $contentNs = $document->getNamespaces(true)['content'] ?? null;

        foreach ($channel->item as $entry) {
            try {
                $url = (string) ($entry->link ?? '');
                $title = (string) ($entry->title ?? '');
                $description = (string) ($entry->description ?? '');

                // Try content:encoded for full body
                $body = $description;
                if ($contentNs) {
                    $content = $entry->children($contentNs);
                    if (isset($content->encoded)) {
                        $body = (string) $content->encoded;
                    }
                }

                $pubDate = (string) ($entry->pubDate ?? '');
                $publishedAt = $pubDate ? \Carbon\Carbon::parse($pubDate) : null;

                if (empty($url)) {
                    continue;
                }

                $item = new CompetitorContentItem([
                    'source_id' => $source->id,
                    'external_url' => $url,
                    'title' => $title ?: null,
                    'excerpt' => $this->extractExcerpt($description),
                    'body' => strip_tags($body) ?: null,
                    'published_at' => $publishedAt,
                    'crawled_at' => now(),
                    'content_hash' => md5($url.$title.$body),
                    'metadata' => ['source' => 'rss'],
                ]);

                $items->push($item);
            } catch (\Throwable $e) {
                Log::warning('RssCrawler: failed to parse item', ['error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    private function parseAtom(CompetitorSource $source, \SimpleXMLElement $document): Collection
    {
        $items = collect();

        foreach ($document->entry as $entry) {
            try {
                $url = '';
                foreach ($entry->link as $link) {
                    $rel = (string) ($link['rel'] ?? 'alternate');
                    if ($rel === 'alternate' || $rel === '') {
                        $url = (string) ($link['href'] ?? '');
                        break;
                    }
                }

                if (empty($url)) {
                    continue;
                }

                $title = (string) ($entry->title ?? '');
                $summary = (string) ($entry->summary ?? '');
                $content = (string) ($entry->content ?? $summary);
                $published = (string) ($entry->published ?? $entry->updated ?? '');
                $publishedAt = $published ? \Carbon\Carbon::parse($published) : null;

                $item = new CompetitorContentItem([
                    'source_id' => $source->id,
                    'external_url' => $url,
                    'title' => $title ?: null,
                    'excerpt' => $this->extractExcerpt($summary),
                    'body' => strip_tags($content) ?: null,
                    'published_at' => $publishedAt,
                    'crawled_at' => now(),
                    'content_hash' => md5($url.$title.$content),
                    'metadata' => ['source' => 'atom'],
                ]);

                $items->push($item);
            } catch (\Throwable $e) {
                Log::warning('RssCrawler: failed to parse Atom entry', ['error' => $e->getMessage()]);
            }
        }

        return $items;
    }

    private function extractExcerpt(string $html, int $maxLength = 300): ?string
    {
        $text = strip_tags($html);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (empty($text)) {
            return null;
        }

        return mb_strlen($text) > $maxLength
            ? mb_substr($text, 0, $maxLength).'...'
            : $text;
    }
}
