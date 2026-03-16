<?php

namespace App\Http\Resources;

use App\Models\PipelineTemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineTemplateVersion */
class PipelineTemplateVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'version' => $this->version,
            'changelog' => $this->changelog,
            'is_latest' => $this->is_latest,
            'published_at' => $this->published_at?->toIso8601String(),
            'definition' => $this->when(
                $request->routeIs('api.pipeline-templates.versions.show'),
                fn () => $this->definition,
            ),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
