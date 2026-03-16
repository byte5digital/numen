<?php

namespace App\Http\Resources;

use App\Models\DifferentiationAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DifferentiationAnalysis */
class DifferentiationAnalysisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'content_id' => $this->content_id,
            'brief_id' => $this->brief_id,
            'competitor_content_id' => $this->competitor_content_id,
            'similarity_score' => $this->similarity_score,
            'differentiation_score' => $this->differentiation_score,
            'angles' => $this->angles,
            'gaps' => $this->gaps,
            'recommendations' => $this->recommendations,
            'analyzed_at' => $this->analyzed_at?->toIso8601String(),
            'competitor_content' => $this->whenLoaded('competitorContent', fn () => new CompetitorContentItemResource($this->competitorContent)),
        ];
    }
}
