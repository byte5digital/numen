<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Performance\SpacePerformanceModel */
class PerformanceModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'attribute_weights' => $this->attribute_weights,
            'top_performers' => $this->top_performers,
            'bottom_performers' => $this->bottom_performers,
            'topic_scores' => $this->topic_scores,
            'persona_scores' => $this->persona_scores,
            'sample_size' => $this->sample_size,
            'model_confidence' => (float) $this->model_confidence,
            'model_version' => $this->model_version,
            'computed_at' => $this->computed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
