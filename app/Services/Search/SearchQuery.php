<?php

namespace App\Services\Search;

/**
 * Immutable search query value object.
 */
final class SearchQuery
{
    /**
     * @param  array<string, string>  $taxonomyFilters
     */
    public function __construct(
        public readonly string $query,
        public readonly string $spaceId,
        public readonly string $mode = 'hybrid',       // 'instant' | 'semantic' | 'hybrid'
        public readonly ?string $contentType = null,
        public readonly ?string $locale = null,
        public readonly array $taxonomyFilters = [],    // ['category' => 'tutorials']
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly bool $highlight = true,
    ) {}
}
