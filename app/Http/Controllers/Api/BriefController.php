<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\User;
use App\Pipelines\PipelineExecutor;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefController extends Controller
{
    public function __construct(
        private PipelineExecutor $executor,
        private AuthorizationService $authz,
    ) {}

    /**
     * Create a content brief and start the pipeline.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:5000',
            'content_type_slug' => 'required|string',
            'target_keywords' => 'nullable|array',
            'target_keywords.*' => 'string|max:200',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string|max:1000',
            'reference_urls' => 'nullable|array',
            'target_locale' => 'nullable|string|max:10',
            'persona_id' => 'nullable|exists:personas,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'pipeline_id' => 'nullable|exists:content_pipelines,id',
        ]);

        $user = $request->user();

        // Verify the user has access to the requested space (space isolation)
        if (! $this->userHasSpaceAccess($user, $validated['space_id'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have access to this space.',
            ], 403);
        }

        // Check content.create permission scoped to this space
        $this->authz->authorize($user, 'content.create', $validated['space_id']);

        $brief = ContentBrief::create(array_merge($validated, [
            'source' => 'manual',
            'status' => 'pending',
        ]));

        // Audit log the creation
        $this->authz->log($user, 'brief.create', $brief, [
            'space_id' => $validated['space_id'],
        ]);

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
                'brief_id' => $brief->id,
                'pipeline_run_id' => $run->id,
                'status' => 'processing',
                'message' => 'Content brief created and pipeline started.',
            ],
        ], 201);
    }

    /**
     * List briefs with optional filters.
     * Results are scoped to spaces the user has access to.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check content.read across all of the user's roles (global or space-scoped).
        // We don't enforce a global-only check here because space-scoped users with
        // content.read should still be able to list briefs (filtered to their spaces).
        $hasReadPermission = $user->roles->contains(function ($role) {
            $perms = $role->permissions ?? [];

            return in_array('*', $perms, true) || in_array('content.read', $perms, true);
        });

        if (! $hasReadPermission) {
            throw new \App\Exceptions\PermissionDeniedException('content.read');
        }

        $query = ContentBrief::query()
            ->with('pipelineRun')
            ->orderByDesc('created_at');

        if ($spaceId = $request->query('space_id')) {
            // Explicit space filter — verify access first
            $this->authz->authorize($user, 'content.read', $spaceId);
            if (! $this->userHasSpaceAccess($user, $spaceId)) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You do not have access to this space.'], 403);
            }
            $query->where('space_id', $spaceId);
        } else {
            // No filter — restrict to spaces the user can access
            $hasGlobalRole = $user->roles()
                ->whereNull('role_user.space_id')
                ->exists();

            if (! $hasGlobalRole) {
                $accessibleSpaces = $user->roles()
                    ->whereNotNull('role_user.space_id')
                    ->pluck('role_user.space_id')
                    ->unique()
                    ->values();

                $query->whereIn('space_id', $accessibleSpaces);
            }
            // If user has a global role, no space restriction needed
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
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $brief = ContentBrief::with(['pipelineRun.content.currentVersion', 'persona'])
            ->findOrFail($id);

        // Check content.read scoped to this brief's space, and verify space membership
        $this->authz->authorize($user, 'content.read', $brief->space_id);
        if (! $this->userHasSpaceAccess($user, $brief->space_id)) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You do not have access to this space.'], 403);
        }

        return response()->json(['data' => $brief]);
    }

    /**
     * Check whether a user has access to a given space.
     * A global role (space_id = null in pivot) grants access to all spaces.
     */
    private function userHasSpaceAccess(User $user, string $spaceId): bool
    {
        return $user->roles()
            ->where(function ($q) use ($spaceId) {
                $q->whereNull('role_user.space_id')
                    ->orWhere('role_user.space_id', $spaceId);
            })
            ->exists();
    }
}
