<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentBlock;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Pipelines\PipelineExecutor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContentAdminController extends Controller
{
    public function index()
    {
        $contents = Content::with(['currentVersion', 'contentType', 'heroImage'])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->through(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->currentVersion?->title ?? 'Untitled',
                'type' => $c->contentType?->slug,
                'status' => $c->status,
                'locale' => $c->locale,
                'quality_score' => $c->currentVersion?->quality_score,
                'seo_score' => $c->currentVersion?->seo_score,
                'author_type' => $c->currentVersion?->author_type,
                'published_at' => $c->published_at?->diffForHumans(),
                'hero_image_url' => $c->heroImage ? '/storage/'.$c->heroImage->path : null,
            ]);

        return Inertia::render('Content/Index', [
            'contents' => $contents,
        ]);
    }

    public function show(string $id)
    {
        $content = Content::with([
            'currentVersion.blocks',
            'contentType',
            'heroImage',
            'versions' => fn ($q) => $q->orderByDesc('version_number'),
        ])->findOrFail($id);

        $version = $content->currentVersion;

        $blocks = $version
            ? $version->blocks()->orderBy('sort_order')->get()->map(fn ($b) => [
                'id' => $b->id,
                'type' => $b->type,
                'sort_order' => $b->sort_order,
                'data' => $b->data ?? [],
                'wysiwyg_override' => $b->wysiwyg_override,
            ])->values()
            : collect();

        return Inertia::render('Content/Show', [
            'content' => [
                'id' => $content->id,
                'slug' => $content->slug,
                'status' => $content->status,
                'locale' => $content->locale,
                'type' => $content->contentType?->slug,
                'type_name' => $content->contentType?->name,
                'taxonomy' => $content->taxonomy,
                'metadata' => $content->metadata,
                'published_at' => $content->published_at?->format('Y-m-d H:i'),
                'created_at' => $content->created_at->diffForHumans(),
                'updated_at' => $content->updated_at->diffForHumans(),
                'hero_image_url' => $content->heroImage ? '/storage/'.$content->heroImage->path : null,
            ],
            'version' => $version ? [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'title' => $version->title,
                'excerpt' => $version->excerpt,
                'body' => $version->body,
                'body_format' => $version->body_format,
                'author_type' => $version->author_type,
                'author_id' => $version->author_id,
                'quality_score' => $version->quality_score,
                'seo_score' => $version->seo_score,
                'seo_data' => $version->seo_data,
                'structured_fields' => $version->structured_fields,
                'ai_metadata' => $version->ai_metadata,
                'change_reason' => $version->change_reason,
                'created_at' => $version->created_at->diffForHumans(),
            ] : null,
            'versions' => $content->versions->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'title' => $v->title,
                'author_type' => $v->author_type,
                'quality_score' => $v->quality_score,
                'seo_score' => $v->seo_score,
                'created_at' => $v->created_at->diffForHumans(),
                'is_current' => $v->id === $content->current_version_id,
            ]),
            'blocks' => $blocks,
            'blockTypes' => ContentBlock::allTypes(),
        ]);
    }

    public function addBlock(Request $request, string $id)
    {
        $content = Content::findOrFail($id);
        $version = $content->currentVersion;

        abort_if(! $version, 422, 'No current version to add blocks to.');

        $data = $request->validate([
            'type' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $maxOrder = $version->blocks()->max('sort_order') ?? -1;

        $block = $version->blocks()->create([
            'type' => $data['type'],
            'data' => $data['data'] ?? [],
            'sort_order' => $data['sort_order'] ?? $maxOrder + 1,
        ]);

        return back()->with('success', 'Block added.');
    }

    public function updateBlock(Request $request, string $id, string $blockId)
    {
        $content = Content::findOrFail($id);
        $block = ContentBlock::where('content_version_id', $content->current_version_id)
            ->findOrFail($blockId);

        $data = $request->validate([
            'data' => ['nullable', 'array'],
            'wysiwyg_override' => ['nullable', 'string'],
        ]);

        $block->update($data);

        return back()->with('success', 'Block saved.');
    }

    public function deleteBlock(string $id, string $blockId)
    {
        $content = Content::findOrFail($id);
        $block = ContentBlock::where('content_version_id', $content->current_version_id)
            ->findOrFail($blockId);

        $block->delete();

        return back()->with('success', 'Block deleted.');
    }

    public function reorderBlocks(Request $request, string $id)
    {
        $content = Content::findOrFail($id);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'string'],
        ]);

        foreach ($data['order'] as $sortOrder => $blockId) {
            ContentBlock::where('content_version_id', $content->current_version_id)
                ->where('id', $blockId)
                ->update(['sort_order' => $sortOrder]);
        }

        return back()->with('success', 'Order saved.');
    }

    public function destroy(string $id)
    {
        $content = Content::findOrFail($id);

        // Delete versions, blocks, and the content itself
        foreach ($content->versions as $version) {
            $version->blocks()->delete();
        }
        $content->versions()->delete();
        $content->delete();

        return redirect()->route('admin.content')->with('success', 'Content deleted.');
    }

    public function generateImage(string $id)
    {
        $content = Content::findOrFail($id);

        \App\Jobs\GenerateContentImage::dispatch($content);

        return back()->with('success', 'Hero image generation queued — it will appear shortly.');
    }

    public function updateStatus(Request $request, string $id)
    {
        $content = Content::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'published' && ! $content->published_at) {
            $updates['published_at'] = now();
        }

        $content->update($updates);

        return back()->with('success', "Status updated to {$validated['status']}.");
    }

    public function createUpdateBrief(Request $request, string $id, PipelineExecutor $executor)
    {
        $content = Content::with(['currentVersion', 'contentType'])->findOrFail($id);

        $validated = $request->validate([
            'prompt' => 'required|string|min:5|max:2000',
        ]);

        $version = $content->currentVersion;

        $brief = ContentBrief::create([
            'space_id' => $content->space_id,
            'content_id' => $content->id,
            'title' => 'Update: '.($version?->title ?? $content->slug),
            'description' => $validated['prompt'],
            'content_type_slug' => $content->contentType?->slug ?? 'blog_post',
            'target_locale' => $content->locale,
            'source' => 'update_brief',
            'status' => 'pending',
        ]);

        $pipeline = ContentPipeline::where('space_id', $content->space_id)
            ->where('is_active', true)
            ->firstOrFail();

        $executor->start($brief, $pipeline, $content);

        return redirect("/admin/briefs/{$brief->id}")->with('success', 'Update brief created — pipeline is running!');
    }
}
