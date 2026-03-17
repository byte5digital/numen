<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Performance\PerformanceCorrelation */
class PerformanceCorrelationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'content_id' => $this->content_id,
            'attribute_name' => $this->attribute_name,
            'metric_name' => $this->metric_name,
            'correlation_coefficient' => (float) $this->correlation_coefficient,
            'p_value' => (float) $this->p_value,
            'sample_size' => $this->sample_size,
            'insight' => $this->insight,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
