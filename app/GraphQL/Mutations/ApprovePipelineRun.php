<?php

namespace App\GraphQL\Mutations;

use App\Models\PipelineRun;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApprovePipelineRun
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): PipelineRun
    {
        $user = Auth::user();
        $run = PipelineRun::with(['pipeline', 'content'])->findOrFail($args['id']);
        $this->authz->authorize($user, 'pipeline.approve', $run->pipeline->space_id);

        if ($run->status !== 'paused_for_review') {
            throw ValidationException::withMessages([
                'id' => ['This pipeline run is not awaiting review (current status: '.$run->status.').'],
            ]);
        }

        // Publish associated content (mirrors PipelineAdminController::approveRun)
        $content = $run->content;
        if ($content) {
            $content->publish();
        }

        $run->markCompleted();

        $this->authz->log($user, 'pipeline.approve', $run);

        return $run->fresh(['pipeline', 'content']);
    }
}
