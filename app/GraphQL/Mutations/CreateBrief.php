<?php

namespace App\GraphQL\Mutations;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Pipelines\PipelineExecutor;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;

class CreateBrief
{
    public function __construct(
        private readonly AuthorizationService $authz,
        private readonly PipelineExecutor $executor,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): ContentBrief
    {
        $user = Auth::user();
        $input = $args['input'];
        $this->authz->authorize($user, 'brief.create', $input['space_id']);

        $brief = ContentBrief::create([
            'space_id' => $input['space_id'],
            'title' => $input['title'],
            'description' => $input['description'] ?? null,
            'content_type_slug' => $input['content_type_slug'],
            'target_locale' => $input['target_locale'] ?? 'en',
            'target_keywords' => $input['target_keywords'] ?? null,
            'priority' => $input['priority'] ?? 'normal',
            'persona_id' => $input['persona_id'] ?? null,
            'pipeline_id' => $input['pipeline_id'] ?? null,
            'source' => 'api',
            'status' => 'pending',
        ]);

        // If a pipeline_id was provided, use it; otherwise look for active pipeline in the space
        $pipelineId = $input['pipeline_id'] ?? null;

        $pipeline = $pipelineId
            ? ContentPipeline::findOrFail($pipelineId)
            : ContentPipeline::where('space_id', $input['space_id'])
                ->where('is_active', true)
                ->first();

        if ($pipeline) {
            $this->executor->start($brief, $pipeline);
        }

        $this->authz->log($user, 'brief.create', $brief);

        return $brief->load(['space']);
    }
}
