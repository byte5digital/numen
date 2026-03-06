<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = Page::with('components')
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $components = $page->components->map(function ($component) {
            $item = [
                'id' => $component->id,
                'type' => $component->type,
                'sort_order' => $component->sort_order,
                'data' => $component->data,
                'wysiwyg_override' => $component->wysiwyg_override,
                'ai_generated' => $component->ai_generated,
            ];

            // Inject live stats into stats_row
            if ($component->type === 'stats_row') {
                $liveStats = $this->liveStats();
                foreach ($item['data']['stats'] as &$stat) {
                    if (isset($stat['value_source'])) {
                        $stat['value'] = (string) ($liveStats[$stat['value_source']] ?? '0');
                    }
                }
            }

            // Inject recent content into content_list
            if ($component->type === 'content_list') {
                $limit = $component->data['limit'] ?? 5;
                $item['recent_content'] = Content::published()
                    ->with(['currentVersion', 'contentType'])
                    ->orderByDesc('published_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($c) => [
                        'slug' => $c->slug,
                        'title' => $c->currentVersion?->title ?? 'Untitled',
                        'excerpt' => $c->currentVersion?->excerpt,
                        'type' => $c->contentType?->slug,
                        'seo_score' => $c->currentVersion?->seo_score,
                    ])
                    ->toArray();
            }

            return $item;
        });

        return response()->json([
            'data' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'meta' => $page->meta,
                'components' => $components,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $pages = Page::published()
            ->select('id', 'slug', 'title', 'meta', 'updated_at')
            ->get();

        return response()->json(['data' => $pages]);
    }

    private function liveStats(): array
    {
        return [
            'published_count' => Content::where('status', 'published')->count(),
            'total_generated' => \App\Models\AIGenerationLog::where('purpose', 'content_generation')->count(),
            'avg_cost' => number_format((\App\Models\AIGenerationLog::avg('cost_usd') ?? 0) * 3, 2),
        ];
    }
}
