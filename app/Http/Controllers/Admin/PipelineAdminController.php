<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use Inertia\Inertia;

class PipelineAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Pipelines/Index', [
            'pipelines' => ContentPipeline::all(),
            'runs' => PipelineRun::with(['brief', 'content.currentVersion', 'pipeline'])
                ->orderByDesc('created_at')
                ->paginate(20)
                ->through(function ($run) {
                    // Find the type of the current stage from the pipeline definition
                    $currentStageType = null;
                    if ($run->current_stage && $run->pipeline) {
                        foreach ($run->pipeline->stages as $stage) {
                            if ($stage['name'] === $run->current_stage) {
                                $currentStageType = $stage['type'];
                                break;
                            }
                        }
                    }

                    return [
                        'id' => $run->id,
                        'brief_title' => $run->brief->title ?? 'Unknown',
                        'status' => $run->status,
                        'current_stage' => $run->current_stage,
                        'current_stage_type' => $currentStageType,
                        'quality_score' => $run->context['last_stage_score'] ?? null,
                        'content_title' => $run->content?->currentVersion?->title,
                        'content_slug' => $run->content?->slug,
                        'created_at' => $run->created_at->diffForHumans(),
                        'updated_at' => $run->updated_at->diffForHumans(),
                    ];
                }),
        ]);
    }

    public function approveRun(string $id, PipelineExecutor $executor)
    {
        $run = PipelineRun::findOrFail($id);

        if ($run->status !== 'paused_for_review') {
            return back()->with('error', 'This run is not awaiting review.');
        }

        // Publish the content
        $content = $run->content;
        if ($content) {
            $content->publish();
        }

        $run->markCompleted();

        return back()->with('success', "Content approved and published: {$content?->currentVersion?->title}");
    }

    public function rejectRun(string $id)
    {
        $run = PipelineRun::findOrFail($id);

        if ($run->status !== 'paused_for_review') {
            return back()->with('error', 'This run is not awaiting review.');
        }

        $run->update(['status' => 'rejected']);

        return back()->with('success', 'Pipeline run rejected.');
    }
}
