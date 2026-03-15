<?php

namespace App\GraphQL\Subscriptions;

use App\Models\Content;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class ContentPublished extends \Nuwave\Lighthouse\Schema\Types\GraphQLSubscription
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
            $this->authz->authorize($user, 'content.view', $spaceId);

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
        if (! $root instanceof Content) {
            return false;
        }

        return $subscriber->args['spaceId'] === $root->space_id;
    }
}
