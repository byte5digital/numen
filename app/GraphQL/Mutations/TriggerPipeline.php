<?php

namespace App\GraphQL\Mutations;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class TriggerPipeline
{
    public function __construct(
        private readonly AuthorizationService $authz,
        private readonly PipelineExecutor $executor,
    ) {}

    /**
     * @param  array{pipelineId: string, contentId: string|null}  $args
     */
    public function __invoke(mixed $root, array $args): PipelineRun
    {
        $user = Auth::user();
        $pipeline = ContentPipeline::findOrFail($args['pipelineId']);
        $this->authz->authorize($user, 'pipeline.trigger', $pipeline->space_id);

        $existingContent = isset($args['contentId'])
            ? Content::findOrFail($args['contentId'])
            : null;

        // Create a minimal brief to drive the pipeline run
        $brief = ContentBrief::create([
            'space_id' => $pipeline->space_id,
            'title' => 'Manual trigger via API',
            'content_type_slug' => 'general',
            'target_locale' => 'en',
            'source' => 'api',
            'priority' => 'normal',
            'status' => 'pending',
            'pipeline_id' => $pipeline->id,
        ]);

        $run = $this->executor->start($brief, $pipeline, $existingContent);

        $this->authz->log($user, 'pipeline.trigger', $run);

        return $run->load(['pipeline', 'content']);
    }
}
