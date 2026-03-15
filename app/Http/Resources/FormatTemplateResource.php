<?php

namespace App\Http\Resources;

use App\Models\FormatTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormatTemplate */
class FormatTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'format_key' => $this->format_key,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'max_tokens' => $this->max_tokens,
            'is_global' => $this->space_id === null, // true for built-in defaults
            'output_schema' => $this->output_schema,
            // Don't expose system_prompt and user_prompt_template in list — only in detail
            'system_prompt' => $this->when($request->routeIs('*.show'), $this->system_prompt),
            'user_prompt_template' => $this->when($request->routeIs('*.show'), $this->user_prompt_template),
        ];
    }
}
