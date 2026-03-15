<?php

namespace App\Http\Resources;

use App\Models\RepurposedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepurposedContent */
class RepurposedContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'format_key' => $this->format_key,
            'status' => $this->status,
            'output' => $this->output,
            'output_parts' => $this->output_parts, // array for twitter threads, null otherwise
            'tokens_used' => $this->tokens_used,
            'is_stale' => $this->is_stale, // accessor: repurposed_at < source content updated_at
            'repurposed_at' => $this->repurposed_at?->toISOString(),
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'persona' => $this->when($this->persona_id, fn () => [
                'id' => $this->persona->id,
                'name' => $this->persona->name,
            ]),
            'format_template' => $this->when($this->format_template_id, fn () => [
                'id' => $this->formatTemplate->id,
                'name' => $this->formatTemplate->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
