<?php

declare(strict_types=1);

namespace App\Http\Resources\Migration;

use App\Models\Migration\MigrationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MigrationItem */
class MigrationItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'migration_session_id' => $this->migration_session_id,
            'source_type_key' => $this->source_type_key,
            'source_id' => $this->source_id,
            'source_hash' => $this->source_hash,
            'numen_content_id' => $this->numen_content_id,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'attempt' => $this->attempt,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
