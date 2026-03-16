<?php

declare(strict_types=1);

namespace App\Services\Migration\Connectors;

interface CmsConnectorInterface
{
    /**
     * Test the connection to the CMS.
     */
    public function testConnection(): bool;

    /**
     * Detect the version of the CMS.
     */
    public function detectVersion(): ?string;

    /**
     * Get all available content types.
     *
     * @return array<string, mixed>
     */
    public function getContentTypes(): array;

    /**
     * Get content items for a specific type with pagination.
     *
     * @return array<int, mixed>
     */
    public function getContentItems(string $typeKey, int $page, int $perPage, ?string $cursor = null): array;

    /**
     * Get the total count of items for a content type.
     */
    public function getTotalCount(string $typeKey): int;

    /**
     * Get media items with pagination.
     *
     * @return array<int, mixed>
     */
    public function getMediaItems(int $page, int $perPage): array;

    /**
     * Get all taxonomies.
     *
     * @return array<string, mixed>
     */
    public function getTaxonomies(): array;

    /**
     * Get all users.
     *
     * @return array<int, mixed>
     */
    public function getUsers(): array;

    /**
     * Check if the CMS supports GraphQL.
     */
    public function supportsGraphQL(): bool;
}
