<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Pipelines\PipelineExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefController extends Controller
{
    public function __construct(
        private PipelineExecutor $executor,
    ) {}

    /**
     * Create a content brief and start the pipeline.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id'          => 'required|exists:spaces,id',
            'title'             => 'required|string|max:500',
            'description'       => 'nullable|string|max:5000',
            'content_type_slug' => 'required|string',
            'target_keywords'   => 'nullable|array',
            'target_keywords.*' => 'string|max:200',
            'requirements'      => 'nullable|array',
            'requirements.*'    => 'string|max:1000',
            'reference_urls'    => 'nullable|array',
            'target_locale'     => 'nullable|string|max:10',
            'persona_id'        => 'nullable|exists:personas,id',
            'priority'          => 'nullable|in:low,normal,high,urgent',
            'pipeline_id'       => 'nullable|exists:content_pipelines,id',
        ]);

        $brief = ContentBrief::create(array_merge($validated, [
            'source' => 'manual',
            'status' => 'pending',
        ]));

        // Find or use the specified pipeline
        $pipeline = $validated['pipeline_id'] ?? null
            ? ContentPipeline::findOrFail($validated['pipeline_id'])
            : ContentPipeline::where('space_id', $validated['space_id'])
                ->where('is_active', true)
                ->firstOrFail();

        // Start the pipeline
        $run = $this->executor->start($brief, $pipeline);

        return response()->json([
            'data' => [
                'brief_id'        => $brief->id,
                'pipeline_run_id' => $run->id,
                'status'          => 'processing',
                'message'         => 'Content brief created and pipeline started.',
            ],
        ], 201);
    }

    /**
     * List briefs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContentBrief::query()
            ->with('pipelineRun')
            ->orderByDesc('created_at');

        if ($spaceId = $request->query('space_id')) {
            $query->where('space_id', $spaceId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    /**
     * Get brief details with pipeline status.
     */
    public function show(string $id): JsonResponse
    {
        $brief = ContentBrief::with(['pipelineRun.content.currentVersion', 'persona'])
            ->findOrFail($id);

        return response()->json(['data' => $brief]);
    }
}
