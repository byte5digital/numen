<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DirectusConnector implements CmsConnectorInterface
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $url,
        private readonly string $token,
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get($this->apiUrl('/collections'));

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function detectVersion(): ?string
    {
        try {
            $response = $this->client()->get($this->apiUrl('/server/info'));

            if ($response->successful()) {
                $data = $response->json();

                return isset($data['data']['directus']['version']) && is_string($data['data']['directus']['version'])
                    ? $data['data']['directus']['version']
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
        try {
            $response = $this->client()->get($this->apiUrl('/collections'));

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getContentItems(string $typeKey, int $page, int $perPage, ?string $cursor = null): array
    {
        try {
            $response = $this->client()->get($this->apiUrl("/items/{$typeKey}"), [
                'page' => $page,
                'limit' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function getTotalCount(string $typeKey): int
    {
        try {
            $response = $this->client()->get($this->apiUrl("/items/{$typeKey}"), [
                'page' => 1,
                'limit' => 1,
                'meta' => 'filter_count',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return (int) ($data['meta']['filter_count'] ?? 0);
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
        try {
            $response = $this->client()->get($this->apiUrl('/files'), [
                'page' => $page,
                'limit' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTaxonomies(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getUsers(): array
    {
        try {
            $response = $this->client()->get($this->apiUrl('/users'));

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function supportsGraphQL(): bool
    {
        return true;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT)
            ->withToken($this->token);
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->url, '/').$path;
    }
}
