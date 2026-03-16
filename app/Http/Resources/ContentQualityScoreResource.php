<?php

namespace App\Http\Resources;

use App\Models\ContentQualityScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentQualityScore */
class ContentQualityScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'content_id' => $this->content_id,
            'content_version_id' => $this->content_version_id,
            'overall_score' => $this->overall_score,
            'dimensions' => [
                'readability' => $this->readability_score,
                'seo' => $this->seo_score,
                'brand' => $this->brand_score,
                'factual' => $this->factual_score,
                'engagement' => $this->engagement_score,
            ],
            'scoring_model' => $this->scoring_model,
            'scoring_duration_ms' => $this->scoring_duration_ms,
            'scored_at' => $this->scored_at->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => ContentQualityScoreItemResource::collection($this->items)),
        ];
    }
}
