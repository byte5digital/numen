<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Space;
use App\Pipelines\PipelineExecutor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageAdminController extends Controller
{
    public function index()
    {
        $pages = Page::with('components')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'status' => $p->status,
                'component_count' => $p->components->count(),
                'updated_at' => $p->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function edit(string $id)
    {
        $page = Page::with('components')->findOrFail($id);

        // Build type schemas for the frontend form renderer
        $typeSchemas = collect(PageComponent::allTypes())
            ->mapWithKeys(fn ($type) => [$type => PageComponent::typeSchema($type)])
            ->toArray();

        return Inertia::render('Pages/Edit', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'status' => $page->status,
                'meta' => $page->meta,
                'components' => $page->components->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'sort_order' => $c->sort_order,
                    'data' => $c->data ?? [],
                    'wysiwyg_override' => $c->wysiwyg_override,
                    'ai_generated' => $c->ai_generated,
                    'locked' => $c->locked,
                    'ai_brief_id' => $c->ai_brief_id,
                ])->values(),
            ],
            'componentTypes' => PageComponent::allTypes(),
            'typeSchemas' => $typeSchemas,
        ]);
    }

    public function updateComponent(Request $request, string $id, string $componentId)
    {
        $component = PageComponent::where('page_id', $id)->findOrFail($componentId);

        $validated = $request->validate([
            'data' => 'nullable|array',
            'wysiwyg_override' => 'nullable|string',
            'locked' => 'boolean',
        ]);

        // Empty string → null for wysiwyg_override
        if (isset($validated['wysiwyg_override']) && trim($validated['wysiwyg_override']) === '') {
            $validated['wysiwyg_override'] = null;
        }

        $component->update($validated);

        return back()->with('success', 'Block saved.');
    }

    public function addComponent(Request $request, string $id)
    {
        $page = Page::findOrFail($id);

        $validated = $request->validate([
            'type' => 'required|string|in:'.implode(',', PageComponent::allTypes()),
        ]);

        $maxOrder = $page->components()->max('sort_order') ?? 0;

        PageComponent::create([
            'page_id' => $page->id,
            'type' => $validated['type'],
            'sort_order' => $maxOrder + 1,
            'data' => [],
        ]);

        return back()->with('success', 'Block added.');
    }

    public function deleteComponent(string $id, string $componentId)
    {
        PageComponent::where('page_id', $id)->findOrFail($componentId)->delete();

        return back()->with('success', 'Block deleted.');
    }

    public function reorderComponents(Request $request, string $id)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'string',
        ]);

        foreach ($validated['order'] as $position => $componentId) {
            PageComponent::where('page_id', $id)
                ->where('id', $componentId)
                ->update(['sort_order' => $position + 1]);
        }

        return back()->with('success', 'Order saved.');
    }

    /**
     * Create a ContentBrief for an AI-generation pass targeting this component.
     * The pipeline runs as normal; the admin reviews the output in Briefs and
     * copies it back to the component fields or WYSIWYG override.
     */
    public function generateComponent(Request $request, string $id, string $componentId, PipelineExecutor $executor)
    {
        $component = PageComponent::where('page_id', $id)->findOrFail($componentId);
        $page = $component->page;
        $space = Space::first();

        $validated = $request->validate([
            'brief_description' => 'required|string|max:2000',
        ]);

        $pipeline = ContentPipeline::where('space_id', $space->id)
            ->where('is_active', true)
            ->firstOrFail();

        $brief = ContentBrief::create([
            'space_id' => $space->id,
            'pipeline_id' => $pipeline->id,
            'title' => "AI generate [{$component->type}] block on page /{$page->slug}",
            'description' => $validated['brief_description'],
            'content_type_slug' => 'blog_post',
            'source' => 'page_component',
            'status' => 'pending',
            'requirements' => [
                'target' => 'page_component',
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'component_id' => $component->id,
                'component_type' => $component->type,
                'instruction' => 'Output structured content suitable for the component type. The admin will review and copy the result into the component fields.',
            ],
        ]);

        $component->update(['ai_brief_id' => $brief->id]);

        $executor->start($brief, $pipeline);

        return back()->with('success', 'AI generation started. Check Briefs to review the output.');
    }
}
