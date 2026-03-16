<?php

namespace App\Http\Resources;

use App\Models\CompetitorContentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompetitorContentItem */
class CompetitorContentItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_id' => $this->source_id,
            'external_url' => $this->external_url,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'published_at' => $this->published_at?->toIso8601String(),
            'crawled_at' => $this->crawled_at?->toIso8601String(),
            'content_hash' => $this->content_hash,
            'metadata' => $this->metadata,
        ];
    }
}
