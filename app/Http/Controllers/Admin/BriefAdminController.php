<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\ContentType;
use App\Models\Persona;
use App\Pipelines\PipelineExecutor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BriefAdminController extends Controller
{
    public function index()
    {
        $briefs = ContentBrief::with('pipelineRun')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Briefs/Index', [
            'briefs' => $briefs,
        ]);
    }

    public function create(\Illuminate\Http\Request $request)
    {
        $space = $request->space();

        return Inertia::render('Briefs/Create', [
            'contentTypes' => ContentType::where('space_id', $space?->id)->get(['name', 'slug']),
            'personas' => Persona::where('space_id', $space?->id)->where('is_active', true)->get(['id', 'name', 'role']),
            'spaceId' => $space?->id,
        ]);
    }

    public function show(string $id)
    {
        $brief = ContentBrief::with([
            'pipelineRun.content.currentVersion',
            'pipelineRun.generationLogs',
            'pipelineRun.pipeline',
            'targetContent.currentVersion',
            'persona',
        ])->findOrFail($id);

        return Inertia::render('Briefs/Show', [
            'brief' => $brief,
        ]);
    }

    public function reprocess(string $id, PipelineExecutor $executor)
    {
        $brief = ContentBrief::with('pipelineRun')->findOrFail($id);

        // Delete existing run(s) and their child records so a fresh run starts clean
        if ($run = $brief->pipelineRun) {
            // Delete AI generation logs referencing this run
            \App\Models\AIGenerationLog::where('pipeline_run_id', $run->id)->delete();
            // Delete associated content + versions if exists
            if ($run->content_id) {
                $content = \App\Models\Content::with('versions.blocks')->find($run->content_id);
                if ($content) {
                    foreach ($content->versions as $version) {
                        $version->blocks()->delete();
                    }
                    $content->versions()->delete();
                    $content->delete();
                }
            }
            $run->delete();
        }

        $brief->update(['status' => 'pending']);

        $pipeline = ContentPipeline::where('space_id', $brief->space_id)
            ->where('is_active', true)
            ->firstOrFail();

        $executor->start($brief, $pipeline);

        return redirect("/admin/briefs/{$id}")->with('success', 'Pipeline restarted successfully.');
    }

    public function store(Request $request, PipelineExecutor $executor)
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'content_type_slug' => 'required|string',
            'target_keywords' => 'nullable|array',
            'target_locale' => 'nullable|string|max:10',
            'persona_id' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $brief = ContentBrief::create(array_merge($validated, [
            'source' => 'manual',
            'status' => 'pending',
        ]));

        $pipeline = ContentPipeline::where('space_id', $validated['space_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $executor->start($brief, $pipeline);

        return redirect('/admin/briefs')->with('success', 'Brief created and pipeline started!');
    }
}
