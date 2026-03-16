<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WordPressConnector implements CmsConnectorInterface
{
    private const API_BASE = '/wp-json/wp/v2';

    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $url,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get($this->apiUrl('/'));

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function detectVersion(): ?string
    {
        try {
            $response = $this->client()->get($this->apiUrl('/'));

            if ($response->successful()) {
                $data = $response->json();

                // Generator URL like: https://wordpress.org/?v=6.4.2
                if (isset($data['generator']) && is_string($data['generator'])) {
                    preg_match('/\?v=([\d.]+)/', $data['generator'], $matches);

                    return $matches[1] ?? null;
                }
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
            $response = $this->client()->get($this->apiUrl('/types'));

            if ($response->successful()) {
                return $response->json() ?? [];
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
            $params = [
                'page' => $page,
                'per_page' => $perPage,
                '_embed' => true,
            ];

            // cursor-based pagination via offset (WP doesn't support native cursors)
            if ($cursor !== null) {
                $params['offset'] = (int) $cursor;
                unset($params['page']);
            }

            $response = $this->client()->get($this->apiUrl("/{$typeKey}"), $params);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function getTotalCount(string $typeKey): int
    {
        try {
            $response = $this->client()->get($this->apiUrl("/{$typeKey}"), [
                'per_page' => 1,
                'page' => 1,
            ]);

            if ($response->successful()) {
                $total = $response->header('X-WP-Total');

                return $total !== '' ? (int) $total : 0;
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
            $response = $this->client()->get($this->apiUrl('/media'), [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
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
        try {
            $response = $this->client()->get($this->apiUrl('/taxonomies'));

            if ($response->successful()) {
                return $response->json() ?? [];
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
            $response = $this->client()->get($this->apiUrl('/users'), [
                'per_page' => 100,
            ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function supportsGraphQL(): bool
    {
        try {
            // WPGraphQL plugin exposes /graphql endpoint
            $response = $this->client()->post(
                rtrim($this->url, '/').'/graphql',
                ['query' => '{ __typename }'],
            );

            return $response->successful() && isset($response->json()['data']);
        } catch (ConnectionException) {
            return false;
        }
    }

    private function client(): PendingRequest
    {
        $client = Http::timeout(self::TIMEOUT)
            ->acceptJson()
            ->withHeaders(['Accept' => 'application/json']);

        if ($this->username !== null && $this->password !== null) {
            $client = $client->withBasicAuth($this->username, $this->password);
        }

        return $client;
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->url, '/').self::API_BASE.$path;
    }
}
