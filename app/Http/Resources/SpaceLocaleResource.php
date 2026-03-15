<?php

namespace App\Http\Resources;

use App\Models\SpaceLocale;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SpaceLocale */
class SpaceLocaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'label' => $this->label,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'fallback_locale' => $this->fallback_locale,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
