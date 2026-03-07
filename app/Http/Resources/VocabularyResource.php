<?php

namespace App\Http\Resources;

use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vocabulary */
class VocabularyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'hierarchy' => $this->hierarchy,
            'allow_multiple' => $this->allow_multiple,
            'settings' => $this->settings,
            'sort_order' => $this->sort_order,
            'terms_count' => $this->whenCounted('terms'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
