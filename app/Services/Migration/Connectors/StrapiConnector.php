<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class StrapiConnector implements CmsConnectorInterface
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $url,
        private readonly string $token,
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get($this->apiUrl('/api/content-types'));

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function detectVersion(): ?string
    {
        try {
            $response = $this->client()->get(rtrim($this->url, '/').'/admin/information');

            if ($response->successful()) {
                $data = $response->json();

                return isset($data['data']['strapiVersion']) && is_string($data['data']['strapiVersion'])
                    ? $data['data']['strapiVersion']
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
            $response = $this->client()->get($this->apiUrl('/api/content-types'));

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
            $response = $this->client()->get($this->apiUrl("/api/{$typeKey}"), [
                'pagination[page]' => $page,
                'pagination[pageSize]' => $perPage,
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
            $response = $this->client()->get($this->apiUrl("/api/{$typeKey}"), [
                'pagination[page]' => 1,
                'pagination[pageSize]' => 1,
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
        try {
            $response = $this->client()->get($this->apiUrl('/api/upload/files'), [
                'pagination[page]' => $page,
                'pagination[pageSize]' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : [];
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
            $response = $this->client()->get($this->apiUrl('/api/users'));

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
        return false;
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
