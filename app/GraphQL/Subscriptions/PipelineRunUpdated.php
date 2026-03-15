<?php

namespace App\GraphQL\Subscriptions;

use App\Models\PipelineRun;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class PipelineRunUpdated extends \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
{
    public function __construct(private readonly AuthorizationService $authz) {}

    /**
     * Check if subscriber is authorized to listen.
     */
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }

        $runId = $subscriber->args['runId'];
        $run = PipelineRun::with('pipeline')->find($runId);

        if ($run === null) {
            return false;
        }

        try {
            $this->authz->authorize($user, 'pipeline.view', $run->pipeline->space_id);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Filter subscribers who should receive the subscription.
     */
    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        if (! $root instanceof PipelineRun) {
            return false;
        }

        return $subscriber->args['runId'] === $root->id;
    }
}
