<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ContentfulConnector implements CmsConnectorInterface
{
    private const TIMEOUT = 15;

    private const CDA_BASE = 'https://cdn.contentful.com';

    public function __construct(
        private readonly string $spaceId,
        private readonly string $accessToken,
        private readonly string $environment = 'master',
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get($this->apiUrl('/content_types'), [
                'limit' => 1,
            ]);

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function detectVersion(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContentTypes(): array
    {
        try {
            $response = $this->client()->get($this->apiUrl('/content_types'));

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
            $offset = ($page - 1) * $perPage;

            $response = $this->client()->get($this->apiUrl('/entries'), [
                'content_type' => $typeKey,
                'skip' => $offset,
                'limit' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['items'] ?? [];
            }
        } catch (ConnectionException) {
            // ignore
        }

        return [];
    }

    public function getTotalCount(string $typeKey): int
    {
        try {
            $response = $this->client()->get($this->apiUrl('/entries'), [
                'content_type' => $typeKey,
                'skip' => 0,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return (int) ($data['total'] ?? 0);
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
            $offset = ($page - 1) * $perPage;

            $response = $this->client()->get($this->apiUrl('/assets'), [
                'skip' => $offset,
                'limit' => $perPage,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['items'] ?? [];
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
        return [];
    }

    public function supportsGraphQL(): bool
    {
        return true;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT)
            ->withToken($this->accessToken);
    }

    private function apiUrl(string $path): string
    {
        return self::CDA_BASE."/spaces/{$this->spaceId}/environments/{$this->environment}".$path;
    }
}
