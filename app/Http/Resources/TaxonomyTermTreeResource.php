<?php

namespace App\Http\Resources;

use App\Models\TaxonomyTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaxonomyTerm */
class TaxonomyTermTreeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vocabulary_id' => $this->vocabulary_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'path' => $this->path,
            'depth' => $this->depth,
            'sort_order' => $this->sort_order,
            'metadata' => $this->metadata,
            'content_count' => $this->content_count,
            'children' => TaxonomyTermTreeResource::collection($this->whenLoaded('childrenRecursive')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
