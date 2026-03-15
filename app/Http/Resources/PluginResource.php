<?php

namespace App\Http\Resources;

use App\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plugin */
class PluginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $manifest = $this->manifest ?? [];

        $settings = $this->whenLoaded('settings', function () {
            return $this->settings->map(fn ($setting) => [
                'key' => $setting->key,
                'value' => $setting->is_secret ? '***' : $setting->value,
                'is_secret' => $setting->is_secret,
                'space_id' => $setting->space_id,
            ])->values()->all();
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'version' => $this->version,
            'description' => $this->description,
            'status' => $this->status,
            'manifest' => [
                'hooks' => $manifest['hooks'] ?? [],
                'permissions' => $manifest['permissions'] ?? [],
                'settings_schema' => $manifest['settings_schema'] ?? [],
            ],
            'settings' => $settings,
            'installed_at' => $this->installed_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
