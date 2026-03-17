<?php

declare(strict_types=1);

namespace App\Http\Resources\Migration;

use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MigrationTypeMapping */
class MigrationTypeMappingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'migration_session_id' => $this->migration_session_id,
            'source_type_key' => $this->source_type_key,
            'source_type_label' => $this->source_type_label,
            'numen_content_type_id' => $this->numen_content_type_id,
            'numen_type_slug' => $this->numen_type_slug,
            'field_map' => $this->field_map,
            'ai_suggestions' => $this->ai_suggestions,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
