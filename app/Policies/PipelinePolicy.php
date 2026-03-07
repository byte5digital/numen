<?php

namespace App\Policies;

use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;

/**
 * Resource-level authorization for Pipeline models.
 */
class PipelinePolicy
{
    public function __construct(
        private readonly AuthorizationService $authz,
    ) {}

    private function space(?ContentPipeline $pipeline = null): ?Space
    {
        if ($pipeline !== null) {
            return $pipeline->space;
        }

        /** @var Space|null */
        return request()->attributes->get('active_space');
    }

    public function viewAny(User $user): bool
    {
        /** @var Space|null $space */
        $space = request()->attributes->get('active_space');

        return $this->authz->can($user, 'pipeline.run', $space)
            || $this->authz->can($user, 'pipeline.configure', $space);
    }

    public function view(User $user, ContentPipeline $pipeline): bool
    {
        return $this->authz->can($user, 'pipeline.run', $this->space($pipeline))
            || $this->authz->can($user, 'pipeline.configure', $this->space($pipeline));
    }

    public function run(User $user, ContentPipeline $pipeline): bool
    {
        return $this->authz->can($user, 'pipeline.run', $this->space($pipeline));
    }

    public function configure(User $user, ContentPipeline $pipeline): bool
    {
        return $this->authz->can($user, 'pipeline.configure', $this->space($pipeline));
    }

    public function approve(User $user, PipelineRun $run): bool
    {
        $space = $run->pipeline !== null ? $run->pipeline->space : null;

        return $this->authz->can($user, 'pipeline.approve', $space);
    }

    public function reject(User $user, PipelineRun $run): bool
    {
        $space = $run->pipeline !== null ? $run->pipeline->space : null;

        return $this->authz->can($user, 'pipeline.reject', $space);
    }
}
