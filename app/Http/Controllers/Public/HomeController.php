<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Page;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $page = Page::with('components')
            ->published()
            ->where('slug', 'home')
            ->first();

        // Inject live stats into the stats_row component data
        if ($page) {
            $liveStats = $this->liveStats();
            $page->setRelation('components', $page->components->map(function ($component) use ($liveStats) {
                if ($component->type === 'stats_row') {
                    $data = $component->data;
                    foreach ($data['stats'] as &$stat) {
                        if (isset($stat['value_source'])) {
                            $stat['value'] = (string) ($liveStats[$stat['value_source']] ?? '0');
                        }
                    }
                    $component->data = $data;
                }
                if ($component->type === 'content_list') {
                    $component->setAttribute('_recent_content', $this->recentContent($component->data['limit'] ?? 5));
                }

                return $component;
            }));
        }

        return Inertia::render('Public/Home', [
            'page' => $page ? $this->serializePage($page) : null,
        ]);
    }

    private function liveStats(): array
    {
        return [
            'published_count' => Content::where('status', 'published')->count(),
            'total_generated' => \App\Models\AIGenerationLog::where('purpose', 'content_generation')->count(),
            'avg_cost' => number_format((\App\Models\AIGenerationLog::avg('cost_usd') ?? 0) * 3, 2),
        ];
    }

    private function recentContent(int $limit): array
    {
        return Content::published()
            ->with(['currentVersion', 'contentType', 'heroImage'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'slug' => $c->slug,
                'title' => $c->currentVersion->title ?? 'Untitled',
                'excerpt' => $c->currentVersion?->excerpt,
                'type' => $c->contentType->slug,
                'seo_score' => $c->currentVersion?->seo_score,
                'hero_image_url' => $c->heroImage ? $c->heroImage->url : null,
            ])
            ->toArray();
    }

    private function serializePage(Page $page): array
    {
        return [
            'slug' => $page->slug,
            'meta' => $page->meta,
            'components' => $page->components->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'data' => $c->data,
                'wysiwyg_override' => $c->wysiwyg_override,
                'recent_content' => $c->getAttribute('_recent_content'),
            ])->values()->toArray(),
        ];
    }
}
