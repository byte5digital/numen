<?php

namespace App\Http\Resources;

use App\Models\ContentQualityScoreItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentQualityScoreItem */
class ContentQualityScoreItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dimension' => $this->dimension,
            'category' => $this->category,
            'rule_key' => $this->rule_key,
            'label' => $this->label,
            'severity' => $this->severity,
            'score_impact' => $this->score_impact,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
            'metadata' => $this->metadata,
        ];
    }
}
