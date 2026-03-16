<?php

namespace App\Http\Resources;

use App\Models\PipelineTemplateInstall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineTemplateInstall */
class PipelineTemplateInstallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'version_id' => $this->version_id,
            'space_id' => $this->space_id,
            'pipeline_id' => $this->pipeline_id,
            'installed_at' => $this->installed_at->toIso8601String(),
            'config_overrides' => $this->config_overrides,
            'template' => $this->whenLoaded('template', fn () => new PipelineTemplateResource($this->template)),
            'version' => $this->whenLoaded('templateVersion', fn () => new PipelineTemplateVersionResource($this->templateVersion)),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
