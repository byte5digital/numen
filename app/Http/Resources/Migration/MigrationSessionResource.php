<?php

declare(strict_types=1);

namespace App\Http\Resources\Migration;

use App\Models\Migration\MigrationSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MigrationSession */
class MigrationSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'source_cms' => $this->source_cms,
            'source_url' => $this->source_url,
            'source_version' => $this->source_version,
            'status' => $this->status,
            'total_items' => $this->total_items,
            'processed_items' => $this->processed_items,
            'failed_items' => $this->failed_items,
            'skipped_items' => $this->skipped_items,
            'options' => $this->options,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'type_mappings' => MigrationTypeMappingResource::collection($this->whenLoaded('typeMappings')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
