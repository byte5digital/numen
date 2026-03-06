<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $content_version_id
 * @property string $type
 * @property int $sort_order
 * @property array|null $data
 * @property string|null $wysiwyg_override
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ContentVersion $version
 */
class ContentBlock extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_version_id',
        'type',
        'sort_order',
        'data',
        'wysiwyg_override',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    /**
     * Built-in content block types with their field schemas.
     * Extended dynamically from component_definitions table.
     */
    public static function builtinTypes(): array
    {
        return [
            'paragraph' => ['text' => 'wysiwyg'],
            'heading' => ['level' => 'string', 'text' => 'string'],
            'code_block' => ['language' => 'string', 'code' => 'text'],
            'quote' => ['text' => 'text', 'attribution' => 'string'],
            'callout' => ['variant' => 'string', 'title' => 'string', 'body' => 'text'],
            'divider' => [],
            'image' => ['url' => 'string', 'alt' => 'string', 'caption' => 'string'],
            'embed' => ['url' => 'string', 'caption' => 'string'],
        ];
    }

    /**
     * Returns all available types with their schemas, keyed by type.
     * Format: ['paragraph' => ['text' => 'wysiwyg'], 'custom_type' => [...], ...]
     */
    public static function allTypes(): array
    {
        $all = self::builtinTypes();

        // Merge in AI-registered custom types
        ComponentDefinition::all()->each(function ($def) use (&$all) {
            $all[$def->type] = $def->schema ?? [];
        });

        return $all;
    }

    public static function typeSchema(string $type): array
    {
        $builtin = self::builtinTypes();
        if (isset($builtin[$type])) {
            return $builtin[$type];
        }
        $definition = ComponentDefinition::where('type', $type)->first();

        return $definition !== null ? ($definition->schema ?? []) : [];
    }
}
