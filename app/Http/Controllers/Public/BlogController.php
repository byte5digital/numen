<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Content::published()
            ->with(['currentVersion', 'contentType', 'heroImage'])
            ->orderByDesc('published_at');

        $currentType = $request->query('type');
        if ($currentType) {
            $query->ofType($currentType);
        }

        $contents = $query->paginate(10)->through(fn ($c) => [
            'slug'           => $c->slug,
            'title'          => $c->currentVersion?->title ?? 'Untitled',
            'excerpt'        => $c->currentVersion?->excerpt,
            'type'           => $c->contentType?->slug,
            'quality_score'  => $c->currentVersion?->quality_score,
            'seo_score'      => $c->currentVersion?->seo_score,
            'published_at'   => $c->published_at?->diffForHumans(),
            'hero_image_url' => $c->heroImage ? '/storage/' . $c->heroImage->path : null,
        ]);

        $contentTypes = ContentType::select('name', 'slug')->get();

        return Inertia::render('Public/BlogIndex', [
            'contents'     => $contents,
            'contentTypes' => $contentTypes,
            'currentType'  => $currentType,
        ]);
    }

    public function show(string $slug)
    {
        $content = Content::published()
            ->where('slug', $slug)
            ->with(['currentVersion', 'contentType', 'heroImage'])
            ->firstOrFail();

        $version = $content->currentVersion;

        $relatedContent = Content::published()
            ->where('id', '!=', $content->id)
            ->with('currentVersion')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn ($c) => [
                'slug'    => $c->slug,
                'title'   => $c->currentVersion?->title,
                'excerpt' => $c->currentVersion?->excerpt,
            ]);

        // Load content blocks if the version has them
        $blocks = [];
        if ($version) {
            $blocks = $version->blocks()->get()->map(fn ($b) => [
                'id'               => $b->id,
                'type'             => $b->type,
                'sort_order'       => $b->sort_order,
                'data'             => $b->data ?? [],
                'wysiwyg_override' => $b->wysiwyg_override,
            ])->values()->all();
        }

        return Inertia::render('Public/BlogShow', [
            'content' => [
                'slug'           => $content->slug,
                'title'          => $version?->title,
                'excerpt'        => $version?->excerpt,
                'body'           => $version?->body,
                'body_format'    => $version?->body_format,
                'type'           => $content->contentType?->slug,
                'locale'         => $content->locale,
                'taxonomy'       => $content->taxonomy,
                'seo'            => $version?->seo_data,
                'published_at'   => $content->published_at?->format('M d, Y'),
                'updated_at'     => $content->updated_at?->toIso8601String(),
                'hero_image_url' => $content->heroImage ? '/storage/' . $content->heroImage->path : null,
                'meta'           => [
                    'version'       => $version?->version_number,
                    'quality_score' => $version?->quality_score,
                    'seo_score'     => $version?->seo_score,
                    'generated_by'  => $version?->author_type === 'ai_agent' ? 'ai' : 'human',
                ],
            ],
            'blocks'         => $blocks,
            'relatedContent' => $relatedContent,
        ]);
    }
}
