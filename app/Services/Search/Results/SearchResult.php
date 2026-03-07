<?php

namespace App\Services\Search\Results;

/**
 * A single search result item.
 */
final class SearchResult
{
    public function __construct(
        public readonly string $contentId,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly string $url,
        public readonly string $contentType,
        public readonly float $score,
        public readonly string $publishedAt,
        /** @var array<string, mixed> */
        public readonly array $highlights = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    public function withScore(float $score): self
    {
        return new self(
            contentId: $this->contentId,
            title: $this->title,
            excerpt: $this->excerpt,
            url: $this->url,
            contentType: $this->contentType,
            score: $score,
            publishedAt: $this->publishedAt,
            highlights: $this->highlights,
            metadata: $this->metadata,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->contentId,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
            'content_type' => $this->contentType,
            'score' => $this->score,
            'published_at' => $this->publishedAt,
            'highlights' => $this->highlights,
        ];
    }
}
