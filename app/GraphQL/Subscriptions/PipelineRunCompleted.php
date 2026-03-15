<?php

namespace App\GraphQL\Subscriptions;

use App\Models\PipelineRun;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class PipelineRunCompleted extends \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
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

        $spaceId = $subscriber->args['spaceId'];

        try {
            $this->authz->authorize($user, 'pipeline.view', $spaceId);

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

        return $subscriber->args['spaceId'] === $root->pipeline->space_id
            && $root->status === 'completed';
    }
}
