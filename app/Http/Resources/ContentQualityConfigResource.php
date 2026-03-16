<?php

namespace App\Http\Resources;

use App\Models\ContentQualityConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentQualityConfig */
class ContentQualityConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'dimension_weights' => $this->dimension_weights,
            'thresholds' => $this->thresholds,
            'enabled_dimensions' => $this->enabled_dimensions,
            'auto_score_on_publish' => $this->auto_score_on_publish,
            'pipeline_gate_enabled' => $this->pipeline_gate_enabled,
            'pipeline_gate_min_score' => $this->pipeline_gate_min_score,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
