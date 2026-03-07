<?php

namespace App\Services\Search\Results;

/**
 * A single chunk of content prepared for embedding.
 */
final class ContentChunk
{
    public function __construct(
        public readonly string $text,
        public readonly string $type,       // 'title', 'excerpt', 'body', 'block', 'seo'
        public readonly int $index,
        /** @var array<string, mixed> */
        public readonly array $metadata,    // block_type, heading_context, etc.
        public readonly int $tokenCount,
    ) {}
}
