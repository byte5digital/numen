<?php

namespace App\Http\Controllers\Api\Versioning;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\Versioning\VersioningService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function __construct(private VersioningService $versioning) {}

    /**
     * List all versions for a content item.
     */
    public function index(Content $content): JsonResponse
    {
        $this->authorize('view', $content);

        $versions = $content->versions()
            ->select([
                'id', 'version_number', 'label', 'status',
                'author_type', 'author_id', 'change_reason',
                'pipeline_run_id', 'quality_score', 'seo_score',
                'scheduled_at', 'content_hash', 'parent_version_id', 'created_at',
            ])
            ->with('pipelineRun:id,status')
            ->paginate(25);

        return response()->json($versions);
    }

    /**
     * Get a specific version with full details.
     */
    public function show(Content $content, ContentVersion $version): JsonResponse
    {
        $this->authorize('view', $content);

        abort_unless($version->content_id === $content->id, 404);

        $version->load(['blocks', 'pipelineRun', 'parentVersion:id,version_number,label']);

        return response()->json(['data' => $version]);
    }

    /**
     * Create a new draft version.
     */
    public function createDraft(Content $content): JsonResponse
    {
        $this->authorize('modify', $content);

        $draft = $this->versioning->createDraft($content);

        return response()->json(['data' => $draft], 201);
    }

    /**
     * Update a draft version's content.
     */
    public function update(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $this->authorize('modify', $content);

        abort_unless($version->content_id === $content->id, 404);
        abort_unless($version->status === 'draft', 422, 'Only draft versions can be edited.');

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'excerpt' => 'nullable|string|max:2000',
            'body' => 'sometimes|string|max:1048576', // Fix 4: cap body at 1 MB
            'body_format' => 'sometimes|in:markdown,html,blocks',
            'structured_fields' => 'nullable|array',
            'seo_data' => 'nullable|array',
            'change_reason' => 'nullable|string|max:255',
        ]);

        $version->update($validated);

        return response()->json(['data' => $version->fresh()]);
    }

    /**
     * Label/name a version.
     */
    public function label(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $this->authorize('modify', $content);

        abort_unless($version->content_id === $content->id, 404);

        $request->validate(['label' => 'required|string|max:255']);

        $this->versioning->saveVersion($version, $request->string('label')->toString());

        return response()->json(['data' => $version->fresh()]);
    }

    /**
     * Publish a version.
     */
    public function publish(Content $content, ContentVersion $version): JsonResponse
    {
        $this->authorize('publish', $content);

        abort_unless($version->content_id === $content->id, 404);

        $this->versioning->publish($content, $version);

        return response()->json(['message' => 'Published', 'data' => $content->fresh()]);
    }

    /**
     * Schedule a version for future publishing.
     */
    public function schedule(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $this->authorize('schedule', $content);

        abort_unless($version->content_id === $content->id, 404);

        $request->validate([
            'publish_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $schedule = $this->versioning->schedule(
            $content,
            $version,
            Carbon::parse($request->string('publish_at')->toString()),
            $request->string('notes')->toString() ?: null,
        );

        return response()->json(['data' => $schedule], 201);
    }

    /**
     * Cancel a scheduled publish.
     *
     * Fix 6: scope cancellation to the specific version from the route param,
     * not all pending schedules for the content.
     */
    public function cancelSchedule(Content $content, ContentVersion $version): JsonResponse
    {
        $this->authorize('cancelSchedule', $content);

        abort_unless($version->content_id === $content->id, 404);

        // Scope to the specific version — don't cancel unrelated pending schedules
        $content->scheduledPublishes()
            ->where('version_id', $version->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $version->update(['status' => 'draft', 'scheduled_at' => null]);

        $content->update(['status' => 'draft', 'scheduled_publish_at' => null]);

        return response()->json(['message' => 'Schedule cancelled']);
    }

    /**
     * Rollback to a historical version.
     *
     * Fix 5: creates a new DRAFT version only — does not auto-publish.
     * The editor must review and explicitly publish after rollback.
     */
    public function rollback(Content $content, ContentVersion $version): JsonResponse
    {
        $this->authorize('rollback', $content);

        abort_unless($version->content_id === $content->id, 404);

        $newVersion = $this->versioning->rollback($content, $version);

        return response()->json(['data' => $newVersion], 201);
    }

    /**
     * Branch from a version (create editable draft while current stays live).
     */
    public function branch(Content $content, ContentVersion $version, Request $request): JsonResponse
    {
        $this->authorize('modify', $content);

        abort_unless($version->content_id === $content->id, 404);

        $request->validate([
            'label' => 'nullable|string|max:255',
        ]);

        $draft = $this->versioning->branch(
            $content,
            $version,
            $request->string('label')->toString() ?: null,
        );

        return response()->json(['data' => $draft], 201);
    }
}
