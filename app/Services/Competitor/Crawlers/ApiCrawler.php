<?php

namespace App\Services\Competitor\Crawlers;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Generic REST API crawler.
 *
 * Source config JSON schema:
 * {
 *   "endpoint": "https://api.example.com/posts",
 *   "auth": {
 *     "type": "bearer|basic|header",
 *     "token": "...",           // bearer
 *     "username": "...",        // basic
 *     "password": "...",        // basic
 *     "header": "X-API-Key",   // header auth
 *     "value": "..."            // header auth value
 *   },
 *   "params": { "per_page": 50 },
 *   "data_path": "data.items",  // dot-notation path to items array in response
 *   "field_map": {
 *     "url":          "link",
 *     "title":        "title",
 *     "excerpt":      "summary",
 *     "body":         "content",
 *     "published_at": "created_at"
 *   },
 *   "pagination": {
 *     "type": "page",           // "page" or "cursor" or "offset"
 *     "param": "page",
 *     "max_pages": 5
 *   }
 * }
 */
class ApiCrawler implements CrawlerContract
{
    public function supports(string $type): bool
    {
        return $type === 'api';
    }

    /**
     * @return Collection<int, CompetitorContentItem>
     */
    public function crawl(CompetitorSource $source): Collection
    {
        $config = $source->config ?? [];
        $endpoint = $config['endpoint'] ?? $source->url;
        $fieldMap = $config['field_map'] ?? [];
        $dataPath = $config['data_path'] ?? null;
        $pagination = $config['pagination'] ?? null;
        $params = $config['params'] ?? [];

        $items = collect();
        $maxPages = (int) ($pagination['max_pages'] ?? 1);
        $pageParam = $pagination['param'] ?? 'page';

        for ($page = 1; $page <= $maxPages; $page++) {
            $requestParams = $params;
            if ($pagination) {
                $requestParams[$pageParam] = $page;
            }

            $request = Http::timeout(30);
            $request = $this->applyAuth($request, $config['auth'] ?? []);
            $response = $request->get($endpoint, $requestParams);

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();
            $rawItems = $this->extractDataPath($data, $dataPath);

            if (empty($rawItems)) {
                break;
            }

            foreach ($rawItems as $raw) {
                $item = $this->mapItem($source, $raw, $fieldMap);
                if ($item) {
                    $items->push($item);
                }
            }

            // If no pagination configured, only do one page
            if (! $pagination) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Http\Client\PendingRequest  $request
     * @param  array<string, string>  $auth
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function applyAuth($request, array $auth)
    {
        $type = $auth['type'] ?? 'none';

        return match ($type) {
            'bearer' => $request->withToken($auth['token'] ?? ''),
            'basic' => $request->withBasicAuth($auth['username'] ?? '', $auth['password'] ?? ''),
            'header' => $request->withHeaders([$auth['header'] ?? 'X-API-Key' => $auth['value'] ?? '']),
            default => $request,
        };
    }

    /**
     * Extract items from the response using a dot-notation path.
     *
     * @return array<int, mixed>
     */
    private function extractDataPath(mixed $data, ?string $path): array
    {
        if (! $path) {
            return is_array($data) ? $data : [];
        }

        $value = data_get($data, $path);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $fieldMap
     */
    private function mapItem(CompetitorSource $source, array $raw, array $fieldMap): ?CompetitorContentItem
    {
        $get = function (string $key) use ($raw, $fieldMap): mixed {
            $field = $fieldMap[$key] ?? $key;

            return data_get($raw, $field);
        };

        $url = (string) ($get('url') ?? '');
        if (empty($url)) {
            return null;
        }

        $title = (string) ($get('title') ?? '');
        $excerpt = (string) ($get('excerpt') ?? '');
        $body = (string) ($get('body') ?? '');
        $dateStr = $get('published_at');
        $publishedAt = $dateStr ? \Carbon\Carbon::parse((string) $dateStr) : null;

        return new CompetitorContentItem([
            'source_id' => $source->id,
            'external_url' => $url,
            'title' => $title ?: null,
            'excerpt' => $excerpt ?: null,
            'body' => $body ?: null,
            'published_at' => $publishedAt,
            'crawled_at' => now(),
            'content_hash' => md5($url.$title.$body),
            'metadata' => ['source' => 'api', 'raw' => $raw],
        ]);
    }
}
