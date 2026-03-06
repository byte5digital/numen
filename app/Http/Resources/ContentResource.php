<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        // Load blocks from current version (if any)
        $blocks = $version
            ? $version->blocks()->orderBy('sort_order')->get()->map(fn ($b) => [
                'id'               => $b->id,
                'type'             => $b->type,
                'sort_order'       => $b->sort_order,
                'data'             => $b->data ?? [],
                'wysiwyg_override' => $b->wysiwyg_override,
            ])->values()
            : collect();

        return [
            'id'     => $this->id,
            'slug'   => $this->slug,
            'type'   => $this->contentType?->slug,
            'locale' => $this->locale,
            'status' => $this->status,

            // Content from current version
            'title'             => $version?->title,
            'excerpt'           => $version?->excerpt,
            'body'              => $version?->body,
            'body_format'       => $version?->body_format,
            'structured_fields' => $version?->structured_fields,

            // SEO
            'seo' => $version?->seo_data,

            // Taxonomy
            'taxonomy' => $this->taxonomy,

            // Hero image
            'hero_image_url' => $this->heroImage
                ? '/storage/' . $this->heroImage->path
                : null,

            // Media
            'media' => $this->whenLoaded('mediaAssets', fn () =>
                $this->mediaAssets->map(fn ($asset) => [
                    'id'        => $asset->id,
                    'url'       => $asset->path, // TODO: Generate full URL
                    'mime_type' => $asset->mime_type,
                    'role'      => $asset->pivot->role,
                    'alt_text'  => $asset->ai_metadata['alt_text'] ?? null,
                ])
            ),

            // Author
            'author' => [
                'type' => $version?->author_type,
                'id'   => $version?->author_id,
            ],

            // Timestamps
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),

            // Content blocks (block-based rendering; falls back to body when empty)
            'blocks' => $blocks,

            // Meta
            'meta' => [
                'version'       => $version?->version_number,
                'quality_score' => $version?->quality_score,
                'seo_score'     => $version?->seo_score,
                'generated_by'  => $version?->author_type === 'ai_agent' ? 'ai' : 'human',
            ],
        ];
    }
}
