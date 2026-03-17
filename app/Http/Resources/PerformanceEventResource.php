<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Performance\ContentPerformanceEvent */
class PerformanceEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'content_id' => $this->content_id,
            'content_version_id' => $this->content_version_id,
            'variant_id' => $this->variant_id,
            'event_type' => $this->event_type,
            'source' => $this->source,
            'value' => (float) $this->value,
            'metadata' => $this->metadata,
            'session_id' => $this->session_id,
            'visitor_id' => $this->visitor_id,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
