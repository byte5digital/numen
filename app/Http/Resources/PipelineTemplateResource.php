<?php

namespace App\Http\Resources;

use App\Models\PipelineTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineTemplate */
class PipelineTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'schema_version' => $this->schema_version,
            'is_published' => $this->is_published,
            'author_name' => $this->author_name,
            'author_url' => $this->author_url,
            'downloads_count' => $this->downloads_count,
            'latest_version' => $this->whenLoaded('latestVersion', fn () => new PipelineTemplateVersionResource($this->latestVersion)),
            'versions_count' => $this->whenLoaded('versions', fn () => $this->versions->count()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
