<?php

namespace App\Http\Resources;

use App\Models\CompetitorAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompetitorAlert */
class CompetitorAlertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'name' => $this->name,
            'type' => $this->type,
            'conditions' => $this->conditions,
            'is_active' => $this->is_active,
            'notify_channels' => $this->notify_channels,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
