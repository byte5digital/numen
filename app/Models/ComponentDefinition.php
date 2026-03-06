<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ComponentDefinition extends Model
{
    use HasUlids;

    protected $fillable = [
        'type',
        'label',
        'description',
        'schema',
        'vue_template',
        'is_builtin',
        'created_by',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_builtin' => 'boolean',
    ];

    /**
     * Generate a default vue_template from schema fields.
     * Used as fallback rendering when no custom template is provided.
     */
    public function generateDefaultTemplate(): string
    {
        $fields = collect($this->schema)->map(function ($fieldType, $key) {
            $label = ucwords(str_replace('_', ' ', $key));

            return "<div><strong>{$label}:</strong> {{ data.{$key} }}</div>";
        })->join("\n        ");

        return <<<HTML
<div class="p-4 border border-gray-700 rounded-lg bg-gray-900 space-y-2">
    <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">{$this->label}</p>
    {$fields}
</div>
HTML;
    }
}
