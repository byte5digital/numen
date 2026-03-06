<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageComponent extends Model
{
    use HasUlids;

    protected $fillable = [
        'page_id',
        'type',
        'sort_order',
        'data',
        'wysiwyg_override',
        'ai_generated',
        'locked',
        'ai_brief_id',
    ];

    protected $casts = [
        'data' => 'array',
        'ai_generated' => 'boolean',
        'locked' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Component type definitions — drives admin form schema and AI prompts.
     */
    public static function typeSchema(string $type): array
    {
        return match ($type) {
            'hero' => [
                'badge' => 'string',
                'headline' => 'string',
                'subline' => 'text',
                'cta_primary_label' => 'string',
                'cta_primary_href' => 'string',
                'cta_secondary_label' => 'string',
                'cta_secondary_href' => 'string',
            ],
            'stats_row' => [
                'stats' => 'array:value,label,color',
            ],
            'feature_grid' => [
                'headline' => 'string',
                'features' => 'array:icon,title,description',
            ],
            'pipeline_steps' => [
                'headline' => 'string',
                'subline' => 'string',
                'steps' => 'array:name,description,color',
            ],
            'content_list' => [
                'headline' => 'string',
                'subline' => 'string',
                'limit' => 'number',
                'view_all_href' => 'string',
            ],
            'cta_block' => [
                'headline' => 'string',
                'body' => 'text',
                'cta_primary_label' => 'string',
                'cta_primary_href' => 'string',
                'cta_secondary_label' => 'string',
                'cta_secondary_href' => 'string',
            ],
            'tech_stack' => [
                'headline' => 'string',
                'subline' => 'string',
                'items' => 'array:icon,label',
            ],
            'rich_text' => [
                'content' => 'wysiwyg',
            ],
            default => [],
        };
    }

    public static function allTypes(): array
    {
        return ['hero', 'stats_row', 'feature_grid', 'pipeline_steps', 'content_list', 'cta_block', 'tech_stack', 'rich_text'];
    }
}
