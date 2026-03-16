<?php

namespace App\Http\Resources;

use App\Models\CompetitorSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompetitorSource */
class CompetitorSourceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'name' => $this->name,
            'url' => $this->url,
            'feed_url' => $this->feed_url,
            'crawler_type' => $this->crawler_type,
            'config' => $this->config,
            'is_active' => $this->is_active,
            'crawl_interval_minutes' => $this->crawl_interval_minutes,
            'last_crawled_at' => $this->last_crawled_at?->toIso8601String(),
            'error_count' => $this->error_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
