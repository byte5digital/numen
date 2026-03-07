<?php

namespace App\Services\Search\Results;

use Illuminate\Support\Collection;

/**
 * A collection of search results with metadata.
 */
final class SearchResultCollection
{
    /** @var array<int, SearchResult> */
    private array $items;

    private int $total;

    private int $page;

    private int $perPage;

    private string $tierUsed;

    /**
     * @param  array<int, SearchResult>|Collection<int, SearchResult>  $items
     */
    public function __construct(
        array|Collection $items,
        int $total = 0,
        int $page = 1,
        int $perPage = 20,
        string $tierUsed = 'hybrid',
    ) {
        $this->items = is_array($items) ? $items : $items->values()->all();
        $this->total = $total ?: count($this->items);
        $this->page = $page;
        $this->perPage = $perPage;
        $this->tierUsed = $tierUsed;
    }

    public static function empty(string $tierUsed = 'sql'): self
    {
        return new self([], 0, 1, 20, $tierUsed);
    }

    /** @return array<int, SearchResult> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function tierUsed(): string
    {
        return $this->tierUsed;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (SearchResult $r) => $r->toArray(), $this->items),
            'meta' => [
                'total' => $this->total,
                'page' => $this->page,
                'per_page' => $this->perPage,
                'tier_used' => $this->tierUsed,
            ],
        ];
    }
}
