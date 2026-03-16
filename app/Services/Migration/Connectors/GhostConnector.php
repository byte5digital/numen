<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GhostConnector implements CmsConnectorInterface
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get($this->apiUrl('/ghost/api/content/posts/'), [
                'key' => $this->apiKey,
                'limit' => 1,
            ]);

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function detectVersion(): ?string
    {
        try {
            $response = $this->client()->get($this->apiUrl('/ghost/api/content/settings/'), [
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return isset($data['settings']['version']) && is_string($data['settings']['version'])
                    ? $data['settings']['version']
                    : null;
            }
        } catch (ConnectionException) {
            // ignore
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContentTypes(): array
    {
        return [
            'posts' => ['slug' => 'posts', 'name' => 'Posts'],
            'pages' => ['slug' => 'pages', 'name' => 'Pages'],
            'tags' => ['slug' => 'tags', 'name' => 'Tags'],
            'authors' => ['slug' => 'authors', 'name' => 'Authors'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function getContentItems(string $typeKey, int $page, int $perPage, ?string $cursor = null): array
    {
        try {
            $response = $this->client()->get($this->apiUrl("/ghost/api/content/{$typeKey}/"), [
                'key' => $this->apiKey,
                'page' => $page,
                'limit' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data[$typeKey] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function getTotalCount(string $typeKey): int
    {
        try {
            $response = $this->client()->get($this->apiUrl("/ghost/api/content/{$typeKey}/"), [
                'key' => $this->apiKey,
                'page' => 1,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return (int) ($data['meta']['pagination']['total'] ?? 0);
            }
        } catch (ConnectionException) {
            // ignore
        }

        return 0;
    }

    /**
     * @return array<int, mixed>
     */
    public function getMediaItems(int $page, int $perPage): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTaxonomies(): array
    {
        try {
            $response = $this->client()->get($this->apiUrl('/ghost/api/content/tags/'), [
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['tags'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getUsers(): array
    {
        try {
            $response = $this->client()->get($this->apiUrl('/ghost/api/content/authors/'), [
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['authors'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function supportsGraphQL(): bool
    {
        return false;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT);
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->url, '/').$path;
    }
}
