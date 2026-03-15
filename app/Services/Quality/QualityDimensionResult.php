<?php

namespace App\Services\Quality;

final class QualityDimensionResult
{
    /**
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly float $score,
        private readonly array $items = [],
        private readonly array $metadata = [],
    ) {}

    /**
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     * @param  array<string, mixed>  $metadata
     */
    public static function make(float $score, array $items = [], array $metadata = []): self
    {
        return new self(
            score: max(0.0, min(100.0, $score)),
            items: $items,
            metadata: $metadata,
        );
    }

    public function getScore(): float
    {
        return $this->score;
    }

    /** @return array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function countByType(string $type): int
    {
        return count(array_filter($this->items, fn (array $i) => $i['type'] === $type));
    }
}
