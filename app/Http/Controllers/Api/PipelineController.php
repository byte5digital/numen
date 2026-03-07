<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function __construct(private AuthorizationService $authz) {}

    /**
     * Get pipeline run details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.read');

        $run = PipelineRun::with(['content.currentVersion', 'brief', 'generationLogs'])
            ->findOrFail($id);

        return response()->json(['data' => $run]);
    }

    /**
     * Approve a paused pipeline run. Requires pipeline.approve permission.
     * Only users with explicit approval rights may unblock AI generations.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $run = PipelineRun::with('pipeline')->findOrFail($id);

        // Verify the user has pipeline.approve permission scoped to the run's space.
        $spaceId = $run->pipeline->space_id;
        $this->authz->authorize($request->user(), 'pipeline.approve', $spaceId);

        if ($run->status !== 'paused_for_review') {
            return response()->json(['error' => 'Run is not awaiting review'], 422);
        }

        app(PipelineExecutor::class)->advance($run, [
            'stage' => $run->current_stage,
            'success' => true,
            'summary' => 'Approved by human reviewer',
        ]);

        $this->authz->log($request->user(), 'pipeline.approve', $run, [
            'pipeline_run_id' => $run->id,
            'stage' => $run->current_stage,
            'space_id' => $spaceId,
        ]);

        return response()->json(['data' => ['status' => 'approved']]);
    }
}
