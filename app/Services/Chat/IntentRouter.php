<?php

namespace App\Services\Chat;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\Space;
use App\Models\User;
use App\Pipelines\PipelineExecutor;
use Illuminate\Support\Facades\Log;

/**
 * Routes extracted intents to the appropriate service calls.
 *
 * Maps intent actions to existing model/service methods directly —
 * no HTTP round-trips, no duplicated business logic.
 *
 * Returns: {success: bool, result: mixed, message: string}
 */
class IntentRouter
{
    public function __construct(
        private readonly PipelineExecutor $pipelineExecutor,
    ) {}

    /**
     * Route an intent to the appropriate service call.
     *
     * @param  array<string, mixed>  $intent
     * @return array{success: bool, result: mixed, message: string}
     */
    public function route(array $intent, User $user, Space $space): array
    {
        $action = $intent['action'] ?? 'query.generic';
        $params = $intent['params'] ?? [];

        try {
            return match ($action) {
                'content.query' => $this->handleContentQuery($params, $space),
                'content.create' => $this->handleContentCreate($params, $user, $space),
                'content.update' => $this->handleContentUpdate($params, $space),
                'content.delete' => $this->handleContentDelete($params, $space),
                'content.publish' => $this->handleContentStatusChange($params, $space, 'published'),
                'content.unpublish' => $this->handleContentStatusChange($params, $space, 'draft'),
                'pipeline.trigger' => $this->handlePipelineTrigger($params, $user, $space),
                'query.generic' => ['success' => true, 'result' => null, 'message' => 'No action required.'],
                default => [
                    'success' => false,
                    'result' => null,
                    'message' => "Unknown action: {$action}",
                ],
            };
        } catch (\Throwable $e) {
            Log::error('IntentRouter: action failed', [
                'action' => $action,
                'space_id' => $space->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'result' => null,
                'message' => 'Action failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handleContentQuery(array $params, Space $space): array
    {
        $query = Content::query()
            ->where('space_id', $space->id)
            ->with(['currentVersion', 'contentType']);

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['type'])) {
            $query->whereHas('contentType', fn ($q) => $q->where('slug', $params['type']));
        }

        if (isset($params['locale'])) {
            $query->where('locale', $params['locale']);
        }

        if (isset($params['search'])) {
            $query->whereHas('currentVersion', fn ($q) => $q->where('title', 'like', '%'.$params['search'].'%'));
        }

        $limit = min((int) ($params['limit'] ?? 10), 50);
        $items = $query->orderByDesc('updated_at')->limit($limit)->get();

        $result = $items->map(fn (Content $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->currentVersion !== null ? $c->currentVersion->title ?? 'Untitled' : 'Untitled',
            'status' => $c->status,
            'locale' => $c->locale,
            'type' => $c->contentType?->slug,
            'updated_at' => $c->updated_at->diffForHumans(),
        ])->values()->all();

        return [
            'success' => true,
            'result' => $result,
            'message' => count($result).' content item(s) found.',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handleContentCreate(array $params, User $user, Space $space): array
    {
        $title = (string) ($params['title'] ?? 'New Content');
        $description = (string) ($params['description'] ?? '');
        $contentTypeSlug = (string) ($params['type'] ?? 'article');
        $locale = (string) ($params['locale'] ?? config('app.locale', 'en'));

        $brief = ContentBrief::create([
            'space_id' => $space->id,
            'title' => $title,
            'description' => $description,
            'content_type_slug' => $contentTypeSlug,
            'target_locale' => $locale,
            'source' => 'chat',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $pipeline = ContentPipeline::where('space_id', $space->id)
            ->where('is_active', true)
            ->first();

        if ($pipeline) {
            $this->pipelineExecutor->start($brief, $pipeline);

            return [
                'success' => true,
                'result' => ['brief_id' => $brief->id],
                'message' => "Content brief created and pipeline triggered. Brief ID: {$brief->id}",
            ];
        }

        return [
            'success' => true,
            'result' => ['brief_id' => $brief->id],
            'message' => "Content brief created (no active pipeline found). Brief ID: {$brief->id}",
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handleContentUpdate(array $params, Space $space): array
    {
        $contentId = (string) ($params['content_id'] ?? '');

        if ($contentId === '') {
            return ['success' => false, 'result' => null, 'message' => 'content_id is required for update.'];
        }

        $content = Content::where('space_id', $space->id)->findOrFail($contentId);

        $allowed = ['slug', 'locale', 'metadata'];
        $updates = array_intersect_key($params, array_flip($allowed));

        if (! empty($updates)) {
            $content->update($updates);
        }

        return [
            'success' => true,
            'result' => ['content_id' => $content->id],
            'message' => "Content {$content->id} updated.",
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handleContentDelete(array $params, Space $space): array
    {
        $contentId = (string) ($params['content_id'] ?? '');

        if ($contentId === '') {
            return ['success' => false, 'result' => null, 'message' => 'content_id is required for delete.'];
        }

        $content = Content::where('space_id', $space->id)->findOrFail($contentId);

        foreach ($content->pipelineRuns as $run) {
            $run->generationLogs()->delete();
            $run->versions()->update(['pipeline_run_id' => null]);
            $run->delete();
        }

        foreach ($content->versions as $version) {
            $version->blocks()->delete();
        }

        $content->versions()->delete();
        $content->mediaAssets()->detach();
        $content->update(['hero_image_id' => null]);
        $content->delete();

        return [
            'success' => true,
            'result' => ['deleted_id' => $contentId],
            'message' => "Content {$contentId} has been permanently deleted.",
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handleContentStatusChange(array $params, Space $space, string $status): array
    {
        $contentId = (string) ($params['content_id'] ?? '');

        if ($contentId === '') {
            return ['success' => false, 'result' => null, 'message' => 'content_id is required.'];
        }

        $content = Content::where('space_id', $space->id)->findOrFail($contentId);

        $updates = ['status' => $status];
        if ($status === 'published' && ! $content->published_at) {
            $updates['published_at'] = now();
        }

        $content->update($updates);

        $label = $status === 'published' ? 'published' : 'unpublished';

        return [
            'success' => true,
            'result' => ['content_id' => $content->id, 'status' => $status],
            'message' => "Content {$content->id} has been {$label}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{success: bool, result: mixed, message: string}
     */
    private function handlePipelineTrigger(array $params, User $user, Space $space): array
    {
        $briefId = (string) ($params['brief_id'] ?? '');

        if ($briefId === '') {
            return ['success' => false, 'result' => null, 'message' => 'brief_id is required to trigger a pipeline.'];
        }

        $brief = ContentBrief::where('space_id', $space->id)->findOrFail($briefId);

        $pipeline = ContentPipeline::where('space_id', $space->id)
            ->where('is_active', true)
            ->first();

        if (! $pipeline) {
            return ['success' => false, 'result' => null, 'message' => 'No active pipeline found in this space.'];
        }

        $run = $this->pipelineExecutor->start($brief, $pipeline);

        return [
            'success' => true,
            'result' => ['run_id' => $run->id],
            'message' => "Pipeline triggered. Run ID: {$run->id}",
        ];
    }
}
