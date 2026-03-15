<?php

namespace App\GraphQL\Mutations;

use App\Models\PipelineRun;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RejectPipelineRun
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * @param  array{id: string, reason: string|null}  $args
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

        $run->update([
            'status' => 'rejected',
            'stage_results' => array_merge($run->stage_results ?? [], [
                '_rejection' => [
                    'reason' => $args['reason'] ?? null,
                    'rejected_by' => $user->id,
                    'rejected_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $this->authz->log($user, 'pipeline.reject', $run, ['reason' => $args['reason'] ?? null]);

        return $run->fresh(['pipeline', 'content']);
    }
}
