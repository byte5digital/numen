<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Performance\ContentPerformanceSnapshot */
class PerformanceSnapshotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'content_id' => $this->content_id,
            'content_version_id' => $this->content_version_id,
            'period_type' => $this->period_type,
            'period_start' => $this->period_start->toDateString(),
            'views' => $this->views,
            'unique_visitors' => $this->unique_visitors,
            'avg_time_on_page_s' => (float) $this->avg_time_on_page_s,
            'bounce_rate' => (float) $this->bounce_rate,
            'avg_scroll_depth' => (float) $this->avg_scroll_depth,
            'engagement_events' => $this->engagement_events,
            'conversions' => $this->conversions,
            'conversion_rate' => (float) $this->conversion_rate,
            'composite_score' => (float) $this->composite_score,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
